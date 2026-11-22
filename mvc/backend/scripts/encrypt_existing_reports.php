<?php
declare(strict_types=1);

/**
 * CIFRADO DE REGISTROS ANTIGUOS: SOLO CLI, DURANTE UNA VENTANA DE MANTENIMIENTO.
 *
 * Uso:
 *   php scripts/encrypt_existing_reports.php /ruta/privada/config.php --dry-run
 *   php scripts/encrypt_existing_reports.php /ruta/privada/config.php --apply --maintenance-confirmed --backup-confirmed
 *   php scripts/encrypt_existing_reports.php /ruta/privada/config.php --apply --maintenance-confirmed --dev-no-backup-accept-loss
 *
 * La opción sin respaldo es SOLO para datos ficticios de desarrollo: pérdida irreversible posible.
 *
 * Ejecutar JUSTO ANTES de instalar la versión que lee datos cifrados.
 * El sistema anterior no puede leer los registros cifrados. Probar en copia primero.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 3 || !in_array($argv[2], ['--dry-run','--apply'], true)) {
    fwrite(STDERR, "Uso: php encrypt_existing_reports.php /ruta/privada/config.php --dry-run|--apply [--maintenance-confirmed (--backup-confirmed|--dev-no-backup-accept-loss)]\n");
    exit(2);
}
$mode = $argv[2];
if ($mode === '--apply') {
    $maint = in_array('--maintenance-confirmed', $argv, true);
    $withBackup = in_array('--backup-confirmed', $argv, true);
    $devNoBackup = in_array('--dev-no-backup-accept-loss', $argv, true);
    if (!$maint || $withBackup === $devNoBackup) {
        fwrite(STDERR, "Bloqueado: confirmar mantenimiento y elegir SOLO UNA opción: --backup-confirmed o --dev-no-backup-accept-loss (desarrollo, riesgo de pérdida).\n");
        exit(2);
    }
    if ($devNoBackup) {
        fwrite(STDERR, "ADVERTENCIA: migración de desarrollo SIN respaldo. Datos anteriores pueden quedar irrecuperables.\n");
    }
}
$configPath = realpath($argv[1]);
if ($configPath === false || !is_file($configPath)) throw new RuntimeException('Configuración privada no encontrada');
$config = require $configPath;
require __DIR__ . '/../src/Core/ReportCrypto.php';
$crypto = new Demo\Core\ReportCrypto((string)($config['data_encryption_key'] ?? ''));
$pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
    $config['db_user'], $config['db_pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
);
$column = $pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='informe_datos' AND COLUMN_NAME='datos'")->fetchColumn();
if (!in_array(strtolower((string)$column), ['mediumtext','longtext'], true)) {
    throw new RuntimeException('La columna informe_datos.datos debe ser MEDIUMTEXT o LONGTEXT antes de cifrar para soportar la expansión de base64');
}
$signatureColumn = $pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='informe_revisiones' AND COLUMN_NAME='firma_png'")->fetchColumn();
if (!in_array(strtolower((string)$signatureColumn), ['mediumblob','longblob'], true)) {
    throw new RuntimeException('La columna informe_revisiones.firma_png debe ser MEDIUMBLOB o LONGBLOB para cifrar firmas');
}
$signatureRows = $pdo->query("SELECT informe_id,firma_png FROM informe_revisiones WHERE firma_png IS NOT NULL AND LENGTH(firma_png)>0 ORDER BY informe_id")->fetchAll();
$legacySignatures = 0;
foreach($signatureRows as $signatureRow) {
    $id=(int)$signatureRow['informe_id'];
    $bytes=(string)$signatureRow['firma_png'];
    if (Demo\Core\ReportCrypto::isEncrypted($bytes)) {
        $crypto->decryptBytes($id,$bytes,'firma');
    } else {
        if (substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n") throw new RuntimeException("Firma previa inválida en informe $id");
        $legacySignatures++;
    }
}
$rows = $pdo->query("SELECT d.informe_id,d.datos,r.hash_datos,r.estado FROM informe_datos d LEFT JOIN informe_revisiones r ON r.informe_id=d.informe_id ORDER BY d.informe_id")->fetchAll();
$legacy = 0;
foreach ($rows as $row) {
    $id=(int)$row['informe_id'];
    $stored=(string)$row['datos'];
    if (Demo\Core\ReportCrypto::isEncrypted($stored)) {
        $crypto->decrypt($id,$stored);
        continue;
    }
    $plain=$crypto->decrypt($id,$stored,true);
    if ($row['estado']==='aprobado' && (!is_string($row['hash_datos']) || !hash_equals($row['hash_datos'],hash('sha256',$plain)))) {
        throw new RuntimeException("Inconsistencia de integridad en informe $id; migración cancelada");
    }
    $legacy++;
}
fwrite(STDOUT, "Informes: ".count($rows)."; sin cifrar: $legacy; ya cifrados: ".(count($rows)-$legacy)."\n");
if ($mode === '--dry-run') exit(0);
$updated=0;
$select=$pdo->prepare("SELECT datos FROM informe_datos WHERE informe_id=? FOR UPDATE");
$update=$pdo->prepare("UPDATE informe_datos SET datos=? WHERE informe_id=?");
foreach ($rows as $row) {
    $id=(int)$row['informe_id'];
    $pdo->beginTransaction();
    try {
        $select->execute([$id]);
        $current=$select->fetchColumn();
        $select->closeCursor();
        if (!is_string($current)) throw new RuntimeException("No existe el informe $id");
        if (Demo\Core\ReportCrypto::isEncrypted($current)) {
            $crypto->decrypt($id,$current);
        } else {
            $plain=$crypto->decrypt($id,$current,true);
            if ($row['estado']==='aprobado' && !hash_equals((string)$row['hash_datos'],hash('sha256',$plain))) {
                throw new RuntimeException("Cambió la integridad del informe $id");
            }
            $ciphertext=$crypto->encrypt($id,$plain);
            if ($crypto->decrypt($id,$ciphertext)!==$plain) throw new RuntimeException("Error de verificación en informe $id");
            $update->execute([$ciphertext,$id]);
            $updated++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
$signatureSelect=$pdo->prepare("SELECT firma_png FROM informe_revisiones WHERE informe_id=? FOR UPDATE");
$signatureUpdate=$pdo->prepare("UPDATE informe_revisiones SET firma_png=? WHERE informe_id=?");
$updatedSignatures=0;
foreach($signatureRows as $row) {
    $id=(int)$row['informe_id'];
    $pdo->beginTransaction();
    try {
        $signatureSelect->execute([$id]);
        $current=$signatureSelect->fetchColumn();
        $signatureSelect->closeCursor();
        if(!is_string($current)||$current===''){throw new RuntimeException("La firma del informe $id cambió durante la migración");}
        if(Demo\Core\ReportCrypto::isEncrypted($current)) {
            $crypto->decryptBytes($id,$current,'firma');
        } else {
            if(substr($current,0,8)!=="\x89PNG\r\n\x1a\n")throw new RuntimeException("Firma previa inválida en informe $id");
            $encrypted=$crypto->encryptBytes($id,$current,'firma');
            if($crypto->decryptBytes($id,$encrypted,'firma')!==$current)throw new RuntimeException("Error verificando firma $id");
            $signatureUpdate->execute([$encrypted,$id]);
            $updatedSignatures++;
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
$remaining = (int)$pdo->query("SELECT COUNT(*) FROM informe_datos WHERE datos NOT LIKE 'enc:v1:%'")->fetchColumn();
$signatureRemaining=(int)$pdo->query("SELECT COUNT(*) FROM informe_revisiones WHERE firma_png IS NOT NULL AND LENGTH(firma_png)>0 AND LEFT(firma_png,7)!='enc:v1:'")->fetchColumn();
fwrite(STDOUT, "Informes cifrados nuevos: $updated; firmas cifradas nuevas: $updatedSignatures; informes pendientes: $remaining; firmas pendientes: $signatureRemaining\n");
if ($remaining!==0 || $signatureRemaining!==0) exit(1);
