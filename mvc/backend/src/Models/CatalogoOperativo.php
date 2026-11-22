<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
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

    public function empresas(): array {
        return $this->db->connection()->query(
            'SELECT id,razon_social,rut,contacto,email FROM empresas ORDER BY razon_social LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEmpresa(array $data): int {
        $name=self::text($data,'razon_social',160);
        $db=$this->db->connection();
        // Reutilizar la empresa existente en vez de crear una duplicada para la misma razón social.
        $q=$db->prepare('SELECT id FROM empresas WHERE LOWER(TRIM(razon_social))=LOWER(?) LIMIT 1');
        $q->execute([$name]);
        if($q->fetchColumn())throw new RuntimeException('La empresa ya existe',409);
        $q=$db->prepare('INSERT INTO empresas (razon_social) VALUES (?)');
        $q->execute([$name]);
        return (int)$db->lastInsertId();
    }

    public function obras(): array {
        return $this->db->connection()->query(
            'SELECT o.id,o.empresa_id,e.razon_social AS empresa,o.nombre,o.direccion,o.comuna,o.activo '.
            'FROM obras o JOIN empresas e ON e.id=o.empresa_id ORDER BY e.razon_social,o.nombre LIMIT 1000'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obraById(int $id): array {
        $q=$this->db->connection()->prepare(
            'SELECT o.id,o.empresa_id,e.razon_social AS empresa,o.nombre,o.direccion,o.comuna,o.activo '.
            'FROM obras o JOIN empresas e ON e.id=o.empresa_id WHERE o.id=? LIMIT 1'
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
            $q=$pdo->prepare('UPDATE obras SET empresa_id=?,nombre=?,direccion=?,comuna=?,activo=? WHERE id=?');
            $q->execute([$empresaId,$name,$address,$comuna,(int)$data['activo'],$id]);
            if(!$q->rowCount()){
                $check=$pdo->prepare('SELECT id FROM obras WHERE id=?');$check->execute([$id]);
                if(!$check->fetchColumn())throw new RuntimeException('Obra no encontrada',404);
            }
            return $id;
        }catch(PDOException $e){
            if($e->getCode()==='23000')throw new RuntimeException('Ya hay una obra con ese nombre en la empresa',409);
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
}
