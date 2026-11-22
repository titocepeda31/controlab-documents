<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Revisión controlada del registro provisional. NO constituye firma electrónica avanzada. */
final class Revision {
    public function __construct(private Database $db) {}

    public function canReview(int $userId): bool {
        if ($userId < 1) return false;
        $q=$this->db->connection()->prepare(
            'SELECT 1 FROM informe_revisores r JOIN usuarios u ON u.id=r.usuario_id '.
            'JOIN usuario_perfiles p ON p.usuario_id=u.id '.
            "WHERE r.usuario_id=? AND r.habilitado=1 AND u.activo=1 AND p.rol IN ('administrador','administrativo','laboratorista') LIMIT 1"
        );
        $q->execute([$userId]);
        return (bool)$q->fetchColumn();
    }

    /** Devuelve solo metadatos del registro, nunca bytes arbitrarios aportados por el usuario. */
    public function get(int $reportId, string $rawData): array {
        $q=$this->db->connection()->prepare(
            'SELECT r.estado,r.solicitante_id,r.fecha_solicitud,r.revisor_id,r.fecha_revision,r.motivo_devolucion,r.hash_datos,u.email AS revisor_email,r.firma_png '
            .'FROM informe_revisiones r LEFT JOIN usuarios u ON u.id=r.revisor_id WHERE r.informe_id=?'
        );
        $q->execute([$reportId]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['estado'=>'borrador'];
        $signature=$r['firma_png'];
        unset($r['firma_png']);
        $r['integridad_verificada']=$r['estado']==='aprobado' && is_string($r['hash_datos'])
            && hash_equals($r['hash_datos'], hash('sha256',$rawData));
        if ($r['integridad_verificada'] && is_string($signature) && $signature!=='') {
            $bytes=$this->db->reportCrypto()->decryptBytes($reportId,$signature,'firma');
            $r['firma_imagen']='data:image/png;base64,'.base64_encode($bytes);
        }
        return $r;
    }

    public function events(int $reportId): array {
        $q=$this->db->connection()->prepare(
            'SELECT e.accion,e.fecha,e.observacion,u.email AS usuario FROM informe_revision_eventos e '
            .'JOIN usuarios u ON u.id=e.usuario_id WHERE e.informe_id=? ORDER BY e.id DESC LIMIT 50'
        );
        $q->execute([$reportId]);
        return $q->fetchAll();
    }

    public function request(int $reportId, int $userId): void {
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT usuario_id FROM informes WHERE id=? FOR UPDATE');
            $q->execute([$reportId]);
            $owner=$q->fetchColumn();
            if ($owner===false) throw new RuntimeException('Informe no encontrado',404);
            if ((int)$owner!==$userId && !$this->canReview($userId)) throw new RuntimeException('Solo el autor o un revisor autorizado puede enviar este informe a revisión',403);
            $q=$pdo->prepare('SELECT estado FROM informe_revisiones WHERE informe_id=? FOR UPDATE');
            $q->execute([$reportId]);
            $state=$q->fetchColumn();
            if ($state==='aprobado') throw new RuntimeException('El informe ya fue aprobado y no puede volver a revisión',409);
            if ($state==='pendiente') throw new RuntimeException('El informe ya está pendiente de revisión',409);
            $q=$pdo->prepare('INSERT INTO informe_revisiones (informe_id,estado,solicitante_id,fecha_solicitud,revisor_id,fecha_revision,motivo_devolucion,hash_datos,firma_png) '
                .'VALUES (?,\'pendiente\',?,UTC_TIMESTAMP(),NULL,NULL,NULL,NULL,NULL) '
                .'ON DUPLICATE KEY UPDATE estado=\'pendiente\',solicitante_id=VALUES(solicitante_id),fecha_solicitud=VALUES(fecha_solicitud),revisor_id=NULL,fecha_revision=NULL,motivo_devolucion=NULL,hash_datos=NULL,firma_png=NULL');
            $q->execute([$reportId,$userId]);
            $q=$pdo->prepare("INSERT INTO informe_revision_eventos (informe_id,usuario_id,accion) VALUES (?,?,'solicitar')");
            $q->execute([$reportId,$userId]);
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }

    public function returnForChanges(int $reportId, int $userId, string $reason): void {
        if (!$this->canReview($userId)) throw new RuntimeException('No autorizado para revisar informes',403);
        $reason=trim($reason);
        if (mb_strlen($reason)<8 || mb_strlen($reason)>500) throw new InvalidArgumentException('Indica un motivo de devolución de 8 a 500 caracteres');
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT estado FROM informe_revisiones WHERE informe_id=? FOR UPDATE');
            $q->execute([$reportId]);
            if ($q->fetchColumn()!=='pendiente') throw new RuntimeException('El informe no está pendiente de revisión',409);
            $q=$pdo->prepare('UPDATE informe_revisiones SET estado=\'devuelto\',revisor_id=?,fecha_revision=UTC_TIMESTAMP(),motivo_devolucion=?,firma_png=NULL,hash_datos=NULL WHERE informe_id=?');
            $q->execute([$userId,$reason,$reportId]);
            $q=$pdo->prepare("INSERT INTO informe_revision_eventos (informe_id,usuario_id,accion,observacion) VALUES (?,?,'devolver',?)");
            $q->execute([$reportId,$userId,$reason]);
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }

    public function approve(int $reportId, int $userId, string $dataUrl): void {
        if (!$this->canReview($userId)) throw new RuntimeException('No autorizado para aprobar informes',403);
        $png=self::validateSignature($dataUrl);
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT d.datos FROM informes i JOIN informe_datos d ON d.informe_id=i.id WHERE i.id=? FOR UPDATE');
            $q->execute([$reportId]);
            $data=$q->fetchColumn();
            if (!is_string($data)) throw new RuntimeException('Informe no encontrado',404);
            $q=$pdo->prepare('SELECT estado FROM informe_revisiones WHERE informe_id=? FOR UPDATE');
            $q->execute([$reportId]);
            if ($q->fetchColumn()!=='pendiente') throw new RuntimeException('El informe no está pendiente de revisión',409);
            $plaintext=$this->db->reportCrypto()->decrypt($reportId,$data);
            $hash=hash('sha256',$plaintext);
            $q=$pdo->prepare('UPDATE informe_revisiones SET estado=\'aprobado\',revisor_id=?,fecha_revision=UTC_TIMESTAMP(),motivo_devolucion=NULL,hash_datos=?,firma_png=? WHERE informe_id=?');
            $q->bindValue(1,$userId,PDO::PARAM_INT);
            $q->bindValue(2,$hash);
            $q->bindValue(3,$this->db->reportCrypto()->encryptBytes($reportId,$png,'firma'),PDO::PARAM_LOB);
            $q->bindValue(4,$reportId,PDO::PARAM_INT);
            $q->execute();
            $q=$pdo->prepare("INSERT INTO informe_revision_eventos (informe_id,usuario_id,accion,hash_datos) VALUES (?,?,'aprobar',?)");
            $q->execute([$reportId,$userId,$hash]);
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }

    private static function validateSignature(string $value): string {
        if (!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~D',$value,$m)) {
            throw new InvalidArgumentException('Firma inválida: dibuja la firma dentro del recuadro');
        }
        if (strlen($m[1])>350000) throw new InvalidArgumentException('Firma demasiado grande');
        $bytes=base64_decode($m[1],true);
        if (!is_string($bytes) || strlen($bytes)<250 || strlen($bytes)>250000 || substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n") {
            throw new InvalidArgumentException('Imagen de firma PNG inválida');
        }
        $size=@getimagesizefromstring($bytes);
        if (!$size || $size[2]!==IMAGETYPE_PNG || $size[0]<200 || $size[0]>1400 || $size[1]<80 || $size[1]>700) {
            throw new InvalidArgumentException('Dimensiones de la firma inválidas');
        }
        return $bytes;
    }
}
