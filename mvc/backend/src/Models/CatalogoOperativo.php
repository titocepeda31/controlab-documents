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
    public function ufDiaria(): ?array {
        $date=(new \DateTimeImmutable('now',new \DateTimeZone('America/Santiago')))->format('Y-m-d');
        $pdo=$this->db->connection();$q=$pdo->prepare('SELECT fecha,valor,fuente FROM valores_uf WHERE fecha=? AND valor>0');$q->execute([$date]);$row=$q->fetch(PDO::FETCH_ASSOC);if($row)return $row;
        if(!function_exists('curl_init'))return null;
        $url='https://mindicador.cl/api/uf/'.implode('-',array_reverse(explode('-',$date)));
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($status!==200||!is_string($raw))return null;
        $json=json_decode($raw,true);$item=$json['serie'][0]??null;
        if(!is_array($item)||substr((string)($item['fecha']??''),0,10)!==$date||!is_numeric($item['valor']??null)||(float)$item['valor']<=0)return null;
        $value=number_format((float)$item['valor'],2,'.','');
        $q=$pdo->prepare("INSERT INTO valores_uf(fecha,valor,fuente) VALUES(?,?,'mindicador.cl') ON DUPLICATE KEY UPDATE valor=VALUES(valor),fuente=VALUES(fuente)");$q->execute([$date,$value]);return ['fecha'=>$date,'valor'=>$value,'fuente'=>'mindicador.cl'];
    }
    private static function tarifa(array $s,string $moneda,?array $uf): ?string {
        if($s['precio_base']===null||$s['moneda_base']===null)return null;
        $base=(float)$s['precio_base'];
        if($s['moneda_base']!==$moneda){if(!$uf)return null;$base=$moneda==='CLP'?$base*(float)$uf['valor']:$base/(float)$uf['valor'];}
        return number_format($base,$moneda==='CLP'?0:4,'.','');
    }
    private const ENSAYOS_PILOTO = ['gran','ll','lp','att','hum','sol','uscs','proctor','proctor_normal','proctor_modificado','maxmin','cbr','dens','dens_nuclear','dens_cono','cal','por','cubic','la','fines','sand'];
    public static function validarEnsayos(mixed $input): array {
        if(!is_array($input)||count($input)>count(self::ENSAYOS_PILOTO))throw new InvalidArgumentException('Lista de ensayos inválida');
        foreach($input as $code)if(!is_string($code)||!in_array($code,self::ENSAYOS_PILOTO,true))throw new InvalidArgumentException('Ensayo no reconocido');
        if((in_array('proctor',$input,true)&&(in_array('proctor_normal',$input,true)||in_array('proctor_modificado',$input,true)))||(in_array('dens',$input,true)&&(in_array('dens_nuclear',$input,true)||in_array('dens_cono',$input,true))))throw new InvalidArgumentException('No combines método genérico y específico en el mismo servicio');
        return array_values(array_unique($input));
    }
    public function servicios(): array {
        $uf=$this->ufDiaria();$rows=$this->db->connection()->query('SELECT id,nombre,unidad,descripcion,activo,moneda_base,precio_base FROM catalogo_servicios ORDER BY nombre,id LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC);
        $map=[];foreach($this->db->connection()->query('SELECT servicio_id,ensayo_codigo FROM servicio_ensayos ORDER BY ensayo_codigo')->fetchAll(PDO::FETCH_ASSOC) as $m)$map[(int)$m['servicio_id']][]=$m['ensayo_codigo'];
        foreach($rows as &$s){$s['ensayos']=$map[(int)$s['id']]??[];$s['precio_clp']=self::tarifa($s,'CLP',$uf);$s['precio_uf']=self::tarifa($s,'UF',$uf);$s['uf_referencia']=$uf;}unset($s);return $rows;
    }
    public function historialServicio(int $id,array $query): array {
        return Paginator::run($this->db->connection(),'SELECT h.*','FROM servicios_precios_historial h WHERE h.servicio_id=?','h.id DESC',[$id],$query);
    }
    public function saveServicio(array $data,?int $id=null,?int $actor=null): int {
        $ensayos=array_key_exists('ensayos',$data)?self::validarEnsayos($data['ensayos']):null;
        $nombre=self::text($data,'nombre',180);$unidad=self::text($data,'unidad',60);$descripcion=self::text($data,'descripcion',500,false);
        if($id!==null&&!is_bool($data['activo']??null))throw new InvalidArgumentException('Indica si el servicio está activo');
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $old=['moneda_base'=>null,'precio_base'=>null];
            if($id===null){$q=$pdo->prepare('INSERT INTO catalogo_servicios(nombre,unidad,descripcion) VALUES(?,?,?)');$q->execute([$nombre,$unidad,$descripcion]);$id=(int)$pdo->lastInsertId();}
            else{$q=$pdo->prepare('SELECT moneda_base,precio_base FROM catalogo_servicios WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new RuntimeException('Servicio no encontrado',404);$q=$pdo->prepare('UPDATE catalogo_servicios SET nombre=?,unidad=?,descripcion=?,activo=? WHERE id=?');$q->execute([$nombre,$unidad,$descripcion,(int)$data['activo'],$id]);}
            if(array_key_exists('precio_base',$data)&&$data['precio_base']!==''){
                $currency=$data['moneda_base']??null;$price=$data['precio_base'];
                if(!in_array($currency,['CLP','UF'],true)||!is_string($price)||!preg_match($currency==='CLP'?'/^\d{1,12}$/D':'/^\d{1,12}(\.\d{1,4})?$/D',$price))throw new InvalidArgumentException('Tarifa base inválida');
                if($old['moneda_base']!==$currency||$old['precio_base']===null||(float)$old['precio_base']!==(float)$price){
                    $reason=self::text($data,'motivo',500,false);if($old['precio_base']!==null&&trim((string)$reason)==='')throw new InvalidArgumentException('Indica el motivo del cambio de tarifa');
                    $q=$pdo->prepare('UPDATE servicios_precios_historial SET vigente_hasta=NOW() WHERE servicio_id=? AND vigente_hasta IS NULL');$q->execute([$id]);
                    $q=$pdo->prepare('INSERT INTO servicios_precios_historial(servicio_id,moneda_anterior,precio_anterior,moneda_nueva,precio_nuevo,usuario_id,motivo) VALUES(?,?,?,?,?,?,?)');$q->execute([$id,$old['moneda_base'],$old['precio_base'],$currency,$price,$actor,$reason?:'Tarifa inicial']);
                    $q=$pdo->prepare('UPDATE catalogo_servicios SET moneda_base=?,precio_base=?,precio_actualizado_el=NOW(),precio_clp=?,precio_uf=? WHERE id=?');$q->execute([$currency,$price,$currency==='CLP'?$price:null,$currency==='UF'?$price:null,$id]);
                }
            }
            if($ensayos!==null){
                $q=$pdo->prepare('DELETE FROM servicio_ensayos WHERE servicio_id=?');$q->execute([$id]);
                $q=$pdo->prepare('INSERT INTO servicio_ensayos(servicio_id,ensayo_codigo) VALUES(?,?)');
                foreach($ensayos as $code)$q->execute([$id,$code]);
            }
            $pdo->commit();return $id;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new RuntimeException('Ya existe un servicio con ese nombre',409);throw $e;}
    }

    public function cotizaciones(array $query): array {
        $from='FROM cotizaciones c JOIN obras o ON o.id=c.obra_id JOIN empresas e ON e.id=o.empresa_id WHERE 1=1';$params=[];
        $search=Paginator::search($query);
        if($search!==''){$from.=' AND (c.numero LIKE ? OR e.razon_social LIKE ? OR o.nombre LIKE ?)';$params=array_fill(0,3,'%'.$search.'%');}
        foreach(['numero'=>'c.numero','empresa'=>'e.razon_social'] as $key=>$column){
            $value=trim((string)($query[$key]??''));if($value!==''){$from.=" AND $column LIKE ?";$params[]='%'.substr($value,0,160).'%';}
        }
        $dates=[];
        foreach(['desde','hasta'] as $key){$value=(string)($query[$key]??'');if($value==='')continue;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
            if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Fecha inválida');
            $dates[$key]=$value;
            $from.=$key==='desde'?' AND c.creado_el >= ?':' AND c.creado_el < ?';
            $params[]=$key==='desde'?$value:$date->modify('+1 day')->format('Y-m-d');
        }
        if(isset($dates['desde'],$dates['hasta'])&&$dates['desde']>$dates['hasta'])throw new InvalidArgumentException('El rango de fechas es inválido');
        $estado=$query['estado']??'';
        if(in_array($estado,['borrador','enviada','aprobada','rechazada','vencida'],true)){$from.=' AND c.estado=?';$params[]=$estado;}
        $db=$this->db->connection();
        $result=Paginator::run($db,'SELECT c.*,o.nombre AS obra,e.razon_social AS empresa',$from,'c.id DESC',$params,$query);
        $result['resumen']=$db->query('SELECT estado,COUNT(*) AS cantidad FROM cotizaciones GROUP BY estado')->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    public function cotizacion(int $id): array {
        $db=$this->db->connection();
        $q=$db->prepare('SELECT c.*,o.nombre AS obra,o.empresa_id,o.direccion,o.comuna,e.razon_social AS empresa FROM cotizaciones c JOIN obras o ON o.id=c.obra_id JOIN empresas e ON e.id=o.empresa_id WHERE c.id=?');
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Cotización no encontrada',404);
        $q=$db->prepare("SELECT s.*,COALESCE((SELECT SUM(t.cantidad) FROM ot_servicios t JOIN ot_ordenes x ON x.id=t.ot_id WHERE x.cotizacion_id=s.cotizacion_id AND t.servicio_id=s.servicio_id AND x.estado<>'cancelada'),0) AS asignada FROM cotizacion_servicios s WHERE s.cotizacion_id=?");
        $q->execute([$id]);$row['servicios']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT id,numero_ot,estado FROM ot_ordenes WHERE cotizacion_id=? ORDER BY id');$q->execute([$id]);$row['ordenes']=$q->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }
    public function crearCotizacion(array $data): int {
        $numero=self::text($data,'numero',40);$obra=self::positiveId($data['obra_id']??null);
        $moneda=$data['moneda']??'';if(!in_array($moneda,['CLP','UF'],true))throw new InvalidArgumentException('Moneda inválida');
        $lines=$data['servicios']??null;if(!is_array($lines)||!count($lines)||count($lines)>60)throw new InvalidArgumentException('Agrega entre 1 y 60 servicios');
        $uf=$this->ufDiaria();$db=$this->db->connection();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT o.id FROM obras o JOIN empresas e ON e.id=o.empresa_id WHERE o.id=? AND o.activo=1 AND e.activo=1');$q->execute([$obra]);if(!$q->fetchColumn())throw new InvalidArgumentException('Obra inválida');
            $q=$db->prepare('INSERT INTO cotizaciones(numero,obra_id,moneda,condiciones,valor_uf,fecha_uf) VALUES(?,?,?,?,?,?)');$q->execute([$numero,$obra,$moneda,self::text($data,'condiciones',5000,false),$uf['valor']??null,$uf['fecha']??null]);$id=(int)$db->lastInsertId();
            $get=$db->prepare('SELECT nombre,unidad,moneda_base,precio_base,(SELECT MAX(h.id) FROM servicios_precios_historial h WHERE h.servicio_id=c.id AND h.vigente_hasta IS NULL) AS historial_id FROM catalogo_servicios c WHERE id=? AND activo=1 FOR UPDATE');$put=$db->prepare('INSERT INTO cotizacion_servicios(cotizacion_id,servicio_id,nombre,unidad,cantidad,precio_unitario,moneda_base,precio_base,precio_historial_id) VALUES(?,?,?,?,?,?,?,?,?)');$seen=[];
            foreach($lines as $line){
                $sid=self::positiveId($line['servicio_id']??null);if(isset($seen[$sid]))throw new InvalidArgumentException('Servicio repetido');$seen[$sid]=true;
                $qty=$line['cantidad']??null;if($qty!==null){$qty=self::positiveId($qty);if($qty>100000)throw new InvalidArgumentException('Cantidad excesiva');}
                $get->execute([$sid]);$service=$get->fetch(PDO::FETCH_ASSOC);if(!$service)throw new InvalidArgumentException('Servicio inactivo o inexistente');
                $price=self::tarifa($service,$moneda,$uf);
                if($price===null)throw new InvalidArgumentException('Configura la tarifa en '.$moneda.' del servicio '.$service['nombre']);
                $put->execute([$id,$sid,$service['nombre'],$service['unidad'],$qty,$price,$service['moneda_base'],$service['precio_base'],$service['historial_id']]);
            }
            $db->commit();return $id;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new RuntimeException('Número de cotización duplicado',409);throw $e;}
    }
    public function estadoCotizacion(int $id,string $estado): void {
        $previous=['enviada'=>'borrador','aprobada'=>'enviada','rechazada'=>'enviada','vencida'=>'enviada'];
        if(!isset($previous[$estado]))throw new InvalidArgumentException('Estado inválido');
        $q=$this->db->connection()->prepare('UPDATE cotizaciones SET estado=? WHERE id=? AND estado=?');$q->execute([$estado,$id,$previous[$estado]]);
        if(!$q->rowCount())throw new RuntimeException('Transición no permitida',409);
    }
}
