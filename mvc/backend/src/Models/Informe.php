<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

final class Informe {
    private Empresa $empresa;
    private Revision $revision;
    public function __construct(private Database $db) { $this->empresa = new Empresa($db); $this->revision = new Revision($db); }
    public function all(): array {
        return $this->db->connection()->query("SELECT i.id,i.usuario_id,i.numero_informe,i.fecha_informe,i.estado,e.razon_social,COALESCE(r.estado,'borrador') AS revision_estado,r.motivo_devolucion FROM informes i JOIN empresas e ON e.id=i.empresa_id LEFT JOIN informe_revisiones r ON r.informe_id=i.id ORDER BY i.id DESC LIMIT 100")->fetchAll();
    }
    public function find(int $id): array {
        $q=$this->db->connection()->prepare('SELECT i.id,i.usuario_id,i.empresa_id,i.numero_informe,i.tipo_informe,i.fecha_informe,d.datos FROM informes i JOIN informe_datos d ON d.informe_id=i.id WHERE i.id=?');
        $q->execute([$id]);
        $row=$q->fetch();
        if (!$row) throw new RuntimeException('Informe no encontrado',404);
        $raw=$this->db->reportCrypto()->decrypt($id,(string)$row['datos']);
        $row['datos']=json_decode($raw,true) ?? [];
        $row['revision']=$this->revision->get($id,$raw);
        $row['eventos_revision']=$this->revision->events($id);
        return $row;
    }
    public static function allowedFields(array $data): array {
        $keys=['muestra','fechaMuestreo','fechaEnsayo','estructura','zona','predio','camino','sector','kmp','contratista','mandante','nInforme','dch','humedadProctor','dcs','metodo','cbr','ll','lp','ip','chancado','desgaste','humedad','aashto','usc','observaciones','responsable','fechaRegistro'];
        for($i=0;$i<13;$i++) { $keys[]='retenido_'.$i; $keys[]='pasa_'.$i; }
        $allowed=array_flip($keys);
        $filtered=[];
        foreach ($data as $key => $value) {
            if (isset($allowed[$key])) $filtered[$key]=is_scalar($value) ? mb_substr((string)$value,0,2000) : '';
        }
        return $filtered;
    }
    public function create(array $values, ?string $clientUuid=null): int {
        $data=self::allowedFields($values);
        $contractor=trim($data['contratista'] ?? '');
        if ($contractor==='') throw new InvalidArgumentException('Contratista obligatorio');
        $date=($data['fechaEnsayo'] ?? '') ?: ($data['fechaRegistro'] ?? '');
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new InvalidArgumentException('Fecha de ensayo o registro obligatoria');
        [$y,$m,$d]=array_map('intval',explode('-',$date));
        if (!checkdate($m,$d,$y)) throw new InvalidArgumentException('Fecha inválida');
        $pdo=$this->db->connection();
        $userId=(int)($_SESSION['uid'] ?? 0);
        $pdo->beginTransaction();
        try {
            if ($clientUuid !== null) {
                $q=$pdo->prepare('SELECT informe_id FROM informe_envios WHERE usuario_id=? AND client_uuid=? LIMIT 1');
                $q->execute([$userId,$clientUuid]);
                $existing=$q->fetchColumn();
                if ($existing !== false && $existing !== null) {
                    $pdo->commit();
                    return (int)$existing;
                }
                $q=$pdo->prepare('INSERT INTO informe_envios (usuario_id,client_uuid) VALUES (?,?)');
                $q->execute([$userId,$clientUuid]);
            }
            $empresaId=$this->empresa->findOrCreateByName($contractor);
            $q=$pdo->prepare('INSERT INTO informes (empresa_id,usuario_id,numero_informe,tipo_informe,fecha_informe,estado) VALUES (?,?,?,?,?,?)');
            $q->execute([$empresaId,(int)$_SESSION['uid'],($data['nInforme'] ?? '') ?: null,'Registro Calidad Mecánica de Suelo',$date,'borrador']);
            $id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('INSERT INTO informe_datos (informe_id,datos) VALUES (?,?)');
            $plain=json_encode($data,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $q->execute([$id,$this->db->reportCrypto()->encrypt($id,$plain)]);
            if ($clientUuid !== null) {
                $q=$pdo->prepare('UPDATE informe_envios SET informe_id=? WHERE usuario_id=? AND client_uuid=?');
                $q->execute([$id,$userId,$clientUuid]);
            }
            $pdo->commit();
            return $id;
        } catch(Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Un envío repetido puede coincidir con una petición anterior ya confirmada.
            if ($clientUuid !== null && $e instanceof \PDOException && $e->getCode()==='23000') {
                $q=$pdo->prepare('SELECT informe_id FROM informe_envios WHERE usuario_id=? AND client_uuid=? LIMIT 1');
                $q->execute([$userId,$clientUuid]);
                $existing=$q->fetchColumn();
                if ($existing !== false && $existing !== null) return (int)$existing;
            }
            throw $e;
        }
    }
    /** Permite corregir el mismo informe antes de aprobación, conservando su ID y auditoría. */
    public function update(int $id,int $userId,array $values): void {
        $data=self::allowedFields($values);
        $contractor=trim($data['contratista'] ?? '');
        if ($contractor==='') throw new InvalidArgumentException('Contratista obligatorio');
        $date=($data['fechaEnsayo'] ?? '') ?: ($data['fechaRegistro'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new InvalidArgumentException('Fecha obligatoria');
        [$y,$m,$d]=array_map('intval',explode('-',$date));
        if (!checkdate($m,$d,$y)) throw new InvalidArgumentException('Fecha inválida');
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT usuario_id FROM informes WHERE id=? FOR UPDATE');$q->execute([$id]);
            $owner=$q->fetchColumn();
            if ($owner===false) throw new RuntimeException('Informe no encontrado',404);
            if ((int)$owner!==$userId) throw new RuntimeException('Solo el autor puede corregir el informe',403);
            $q=$pdo->prepare('SELECT estado FROM informe_revisiones WHERE informe_id=? FOR UPDATE');$q->execute([$id]);
            $state=$q->fetchColumn();
            if ($state==='aprobado'||$state==='pendiente') throw new RuntimeException('Un informe pendiente o aprobado no puede modificarse',409);
            $empresaId=$this->empresa->findOrCreateByName($contractor);
            $q=$pdo->prepare('UPDATE informes SET empresa_id=?,numero_informe=?,fecha_informe=? WHERE id=?');
            $q->execute([$empresaId,($data['nInforme'] ?? '') ?: null,$date,$id]);
            $q=$pdo->prepare('UPDATE informe_datos SET datos=? WHERE informe_id=?');
            $plain=json_encode($data,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $q->execute([$this->db->reportCrypto()->encrypt($id,$plain),$id]);
            if ($state==='devuelto') {
                $q=$pdo->prepare("INSERT INTO informe_revision_eventos (informe_id,usuario_id,accion) VALUES (?,?,'corregir')");
                $q->execute([$id,$userId]);
            }
            $pdo->commit();
        } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

}
