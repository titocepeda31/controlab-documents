<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use Demo\Core\Paginator;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Catálogos de operación separados de cuentas de acceso (usuarios). */
final class CatalogoOperativo {
    public function __construct(private Database $db) {}

    private static function text(array $data,string $name,int $limit,bool $required=true): string {
        $value=$data[$name]??'';
        if(!is_string($value))throw new InvalidArgumentException('Campo inválido: '.$name);
        $value=trim($value);
        if(($required&&mb_strlen($value)<2)||mb_strlen($value)>$limit)
            throw new InvalidArgumentException('Revisa '.$name.' (máximo '.$limit.' caracteres)');
        return $value;
    }

    private static function positiveId(mixed $id): int {
        if(!is_int($id)&&!(is_string($id)&&ctype_digit($id)))
            throw new InvalidArgumentException('Identificador inválido');
        $value=(int)$id;
        if($value<1)throw new InvalidArgumentException('Identificador inválido');
        return $value;
    }

    public function empresasPage(array $query): array {
        $search=Paginator::search($query);$params=[];$from='FROM empresas e';
        if($search!==''){$from.=' WHERE (e.razon_social LIKE ? OR e.nombre_comercial LIKE ? OR e.rut LIKE ?)';$params=array_fill(0,3,'%'.$search.'%');}
        return Paginator::run($this->db->connection(),'SELECT e.id,e.razon_social,e.nombre_comercial,e.rut,e.contacto,e.telefono,e.email,e.activo,(SELECT COUNT(*) FROM obras o WHERE o.empresa_id=e.id) AS obras_count',$from,'e.razon_social,e.id',$params,$query);
    }
    public function obrasPage(array $query): array {
        $from='FROM obras o JOIN empresas e ON e.id=o.empresa_id LEFT JOIN obra_ubicaciones g ON g.obra_id=o.id WHERE 1=1';$params=[];
        if(isset($query['empresa_id'])){$from.=' AND o.empresa_id=?';$params[]=self::positiveId($query['empresa_id']);}
        $search=Paginator::search($query);
        if($search!==''){$from.=' AND (o.nombre LIKE ? OR o.direccion LIKE ? OR o.comuna LIKE ?)';array_push($params,...array_fill(0,3,'%'.$search.'%'));}
        return Paginator::run($this->db->connection(),'SELECT o.id,o.empresa_id,e.razon_social AS empresa,o.nombre,o.direccion,o.comuna,o.activo,g.latitud,g.longitud',$from,'e.razon_social,o.nombre,o.id',$params,$query);
    }
    public function empresas(): array {
        return $this->db->connection()->query(
            'SELECT id,razon_social,nombre_comercial,rut,contacto,telefono,email,activo FROM empresas ORDER BY razon_social LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveEmpresa(array $data,?int $id=null): int {
        $name=self::text($data,'razon_social',160);
        $commercial=self::text($data,'nombre_comercial',160,false);
        $rut=self::text($data,'rut',20,false);
        $contact=self::text($data,'contacto',160,false);
        $phone=self::text($data,'telefono',40,false);
        $email=self::text($data,'email',190,false);
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Correo electrónico inválido');
        if($id!==null&&!is_bool($data['activo']??null))throw new InvalidArgumentException('Indica el estado de la empresa');
        $db=$this->db->connection();
        $q=$db->prepare('SELECT id FROM empresas WHERE LOWER(TRIM(razon_social))=LOWER(?) AND id<>? LIMIT 1');
        $q->execute([$name,$id??0]);
        if($q->fetchColumn())throw new RuntimeException('La empresa ya existe',409);
        $normalizedRut=strtoupper(preg_replace('/[^0-9K]/i','',$rut));
        if($normalizedRut!==''){
            $q=$db->prepare("SELECT id FROM empresas WHERE UPPER(REPLACE(REPLACE(rut,'.',''),'-',''))=? AND id<>? LIMIT 1");
            $q->execute([$normalizedRut,$id??0]);
            if($q->fetchColumn())throw new RuntimeException('El RUT ya está registrado',409);
        }
        if($id===null){
            $q=$db->prepare('INSERT INTO empresas (razon_social,nombre_comercial,rut,contacto,telefono,email,activo) VALUES (?,?,?,?,?,?,1)');
            $q->execute([$name,$commercial,$rut,$contact,$phone,$email]);
            return (int)$db->lastInsertId();
        }
        $q=$db->prepare('UPDATE empresas SET razon_social=?,nombre_comercial=?,rut=?,contacto=?,telefono=?,email=?,activo=? WHERE id=?');
        $q->execute([$name,$commercial,$rut,$contact,$phone,$email,(int)$data['activo'],$id]);
        if(!$q->rowCount()){
            $check=$db->prepare('SELECT id FROM empresas WHERE id=? AND activo=1');$check->execute([$id]);
            if(!$check->fetchColumn())throw new RuntimeException('Empresa no encontrada',404);
        }
        return $id;
    }
    public function createEmpresa(array $data): int {return $this->saveEmpresa($data);}

    public function obras(): array {
        return $this->db->connection()->query(
            'SELECT o.id,o.empresa_id,e.razon_social AS empresa,o.nombre,o.direccion,o.comuna,o.activo,g.latitud,g.longitud '.
            'FROM obras o JOIN empresas e ON e.id=o.empresa_id LEFT JOIN obra_ubicaciones g ON g.obra_id=o.id ORDER BY e.razon_social,o.nombre LIMIT 1000'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obraById(int $id): array {
        $q=$this->db->connection()->prepare(
            'SELECT o.id,o.empresa_id,e.razon_social AS empresa,o.nombre,o.direccion,o.comuna,o.activo,g.latitud,g.longitud '.
            'FROM obras o JOIN empresas e ON e.id=o.empresa_id LEFT JOIN obra_ubicaciones g ON g.obra_id=o.id WHERE o.id=? LIMIT 1'
        );
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Obra no encontrada',404);
        return $row;
    }

    public function saveObra(array $data,?int $id=null): int {
        $empresaId=self::positiveId($data['empresa_id']??null);
        $name=self::text($data,'nombre',180);
        $address=self::text($data,'direccion',240);
        $comuna=self::text($data,'comuna',120);
        if($id!==null&&(!is_bool($data['activo']??null)))
            throw new InvalidArgumentException('Indica estado activo de la obra');
        $pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT id FROM empresas WHERE id=?');$q->execute([$empresaId]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('La empresa seleccionada no existe');
        try {
            if($id===null){
                $q=$pdo->prepare('INSERT INTO obras (empresa_id,nombre,direccion,comuna) VALUES (?,?,?,?)');
                $q->execute([$empresaId,$name,$address,$comuna]);
                return (int)$pdo->lastInsertId();
            }
            // Dirección y geolocalización deben cambiar atómicamente para
            // evitar enviar al personal a una obra distinta con marcador obsoleto.
            $pdo->beginTransaction();
            $before=$pdo->prepare('SELECT direccion,comuna FROM obras WHERE id=? FOR UPDATE');
            $before->execute([$id]);
            $old=$before->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new RuntimeException('Obra no encontrada',404);
            $q=$pdo->prepare('UPDATE obras SET empresa_id=?,nombre=?,direccion=?,comuna=?,activo=? WHERE id=?');
            $q->execute([$empresaId,$name,$address,$comuna,(int)$data['activo'],$id]);
            if($old['direccion']!==$address||$old['comuna']!==$comuna){
                $geo=$pdo->prepare('DELETE FROM obra_ubicaciones WHERE obra_id=?');
                $geo->execute([$id]);
            }
            $pdo->commit();
            return $id;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($e instanceof PDOException&&$e->getCode()==='23000')
                throw new RuntimeException('Ya hay una obra con ese nombre en la empresa',409);
            throw $e;
        }
    }

    public function personal(): array {
        return $this->db->connection()->query(
            'SELECT id,nombre,cargo,activo,observaciones FROM personal_terreno ORDER BY nombre,id LIMIT 1000'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function savePersonal(array $data,?int $id=null): int {
        $name=self::text($data,'nombre',160);
        $cargo=self::text($data,'cargo',100);
        $note=self::text($data,'observaciones',500,false);
        if($id!==null&&!is_bool($data['activo']??null))
            throw new InvalidArgumentException('Indica estado activo del trabajador');
        $pdo=$this->db->connection();
        if($id===null){
            $q=$pdo->prepare('INSERT INTO personal_terreno (nombre,cargo,observaciones) VALUES (?,?,?)');
            $q->execute([$name,$cargo,$note]);
            return (int)$pdo->lastInsertId();
        }
        $q=$pdo->prepare('UPDATE personal_terreno SET nombre=?,cargo=?,observaciones=?,activo=? WHERE id=?');
        $q->execute([$name,$cargo,$note,(int)$data['activo'],$id]);
        if(!$q->rowCount()){
            $check=$pdo->prepare('SELECT id FROM personal_terreno WHERE id=?');$check->execute([$id]);
            if(!$check->fetchColumn())throw new RuntimeException('Trabajador no encontrado',404);
        }
        return $id;
    }
    public function servicios(): array {
        return $this->db->connection()->query(
            'SELECT id,nombre,unidad,descripcion,activo,precio_clp,precio_uf FROM catalogo_servicios ORDER BY nombre,id LIMIT 1000'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function saveServicePrices(PDO $pdo,int $id,array $prices): void {
        foreach($prices as $key=>$value){
            if(!in_array($key,['precio_clp','precio_uf'],true))continue;
            $q=$pdo->prepare("UPDATE catalogo_servicios SET $key=? WHERE id=?");
            $q->execute([$value,$id]);
        }
    }
    public function saveServicio(array $data,?int $id=null): int {
        $nombre=self::text($data,'nombre',180);
        $unidad=self::text($data,'unidad',60);
        $descripcion=self::text($data,'descripcion',500,false);
        $prices=[];
        foreach(['precio_clp'=>0,'precio_uf'=>4] as $key=>$scale){
            if(!array_key_exists($key,$data))continue;
            $value=$data[$key];
            if($value===null||$value===''){$prices[$key]=null;continue;}
            if(!is_string($value)||!preg_match($scale===0?'/^\d{1,12}$/D':'/^\d{1,12}(\.\d{1,4})?$/D',$value))
                throw new InvalidArgumentException('Precio inválido: '.$key);
            $prices[$key]=$value;
        }
        if($id!==null&&!is_bool($data['activo']??null))
            throw new InvalidArgumentException('Indica si el servicio está activo');
        $pdo=$this->db->connection();
        try {
            if($id===null){
                $q=$pdo->prepare('INSERT INTO catalogo_servicios (nombre,unidad,descripcion) VALUES (?,?,?)');
                $q->execute([$nombre,$unidad,$descripcion]);
                $id=(int)$pdo->lastInsertId();
                $this->saveServicePrices($pdo,$id,$prices);
                return $id;
            }
            $q=$pdo->prepare('UPDATE catalogo_servicios SET nombre=?,unidad=?,descripcion=?,activo=? WHERE id=?');
            $q->execute([$nombre,$unidad,$descripcion,(int)$data['activo'],$id]);
            $this->saveServicePrices($pdo,$id,$prices);
            $q=$pdo->prepare('SELECT id FROM catalogo_servicios WHERE id=?');
            $q->execute([$id]);
            if(!$q->fetchColumn())throw new RuntimeException('Servicio no encontrado',404);
            return $id;
        } catch(PDOException $e) {
            if($e->getCode()==='23000')throw new RuntimeException('Ya existe un servicio con ese nombre',409);
            throw $e;
        }
    }

}
