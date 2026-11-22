<?php
declare(strict_types=1);
require __DIR__ . '/../src/Core/ReportCrypto.php';

use Demo\Core\ReportCrypto;

function expectFailure(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException("Debía fallar: $label");
}
$key=base64_encode(random_bytes(32));
$crypto=new ReportCrypto($key);
$json=json_encode(['muestra'=>'M-01','dch'=>'1.88','nota'=>'áéíóú'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$first=$crypto->encrypt(101,$json);
$second=$crypto->encrypt(101,$json);
if ($first===$second || str_contains($first,'M-01') || !ReportCrypto::isEncrypted($first)) throw new RuntimeException('El cifrado no protege el contenido correctamente');
if ($crypto->decrypt(101,$first)!==$json || $crypto->decrypt(101,$second)!==$json) throw new RuntimeException('Error de ida y vuelta');
expectFailure(fn()=>$crypto->decrypt(102,$first),'intercambio de ID');
expectFailure(fn()=>(new ReportCrypto(base64_encode(random_bytes(32))))->decrypt(101,$first),'clave equivocada');
$raw=base64_decode(substr($first,7),true);
$raw[strlen($raw)-1]=chr(ord($raw[strlen($raw)-1])^1);
expectFailure(fn()=>$crypto->decrypt(101,'enc:v1:'.base64_encode($raw)),'contenido alterado');
expectFailure(fn()=>$crypto->decrypt(101,$json),'rechazo de registro no cifrado');
if ($crypto->decrypt(101,$json,true)!==$json) throw new RuntimeException('Migración de legado fallida');
expectFailure(fn()=>new ReportCrypto('clave-de-prueba'),'clave inválida');
if (hash('sha256',$json)!==hash('sha256',$crypto->decrypt(101,$first))) throw new RuntimeException('La revisión perdió integridad');
echo "OK: cifrado autenticado, diferentes nonces, integridad y legado\n";
