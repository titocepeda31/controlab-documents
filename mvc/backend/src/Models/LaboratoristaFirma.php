<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use InvalidArgumentException;
use PDO;

final class LaboratoristaFirma {
    public function __construct(private Database $db) {}

    public function getForUser(int $userId): ?string {
        if ($userId < 1) throw new InvalidArgumentException('Usuario inválido');
        $q=$this->db->connection()->prepare('SELECT firma_png FROM laboratorista_firmas WHERE usuario_id=?');
        $q->execute([$userId]);
        $stored=$q->fetchColumn();
        if (!is_string($stored) || $stored==='') return null;
        $png=$this->db->reportCrypto()->decryptBytes($userId,$stored,'firma_laboratorista');
        if (substr($png,0,8)!=="\x89PNG\r\n\x1a\n") throw new \RuntimeException('Firma del laboratorista no válida');
        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function save(int $userId,string $dataUrl): void {
        if ($userId < 1) throw new InvalidArgumentException('Usuario inválido');
        if (!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~D',$dataUrl,$m))
            throw new InvalidArgumentException('Se requiere una firma PNG');
        if (strlen($m[1])>350000) throw new InvalidArgumentException('La firma es demasiado grande');
        $png=base64_decode($m[1],true);
        if (!is_string($png)||strlen($png)<150||strlen($png)>250000||substr($png,0,8)!=="\x89PNG\r\n\x1a\n")
            throw new InvalidArgumentException('PNG de firma inválido');
        $size=@getimagesizefromstring($png);
        if (!$size||$size[2]!==IMAGETYPE_PNG||$size[0]<200||$size[0]>1400||$size[1]<80||$size[1]>700)
            throw new InvalidArgumentException('La firma debe tener dimensiones válidas (mínimo 200×80, máximo 1400×700)');
        $encrypted=$this->db->reportCrypto()->encryptBytes($userId,$png,'firma_laboratorista');
        $q=$this->db->connection()->prepare(
            'INSERT INTO laboratorista_firmas (usuario_id,firma_png) VALUES (?,?) '.
            'ON DUPLICATE KEY UPDATE firma_png=VALUES(firma_png),fecha_actualizacion=CURRENT_TIMESTAMP'
        );
        $q->bindValue(1,$userId,PDO::PARAM_INT);
        $q->bindValue(2,$encrypted,PDO::PARAM_LOB);
        $q->execute();
    }

    public function delete(int $userId): void {
        $q=$this->db->connection()->prepare('DELETE FROM laboratorista_firmas WHERE usuario_id=?');
        $q->execute([$userId]);
    }
}
