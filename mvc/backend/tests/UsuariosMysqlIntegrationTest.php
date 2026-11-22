<?php
declare(strict_types=1);
require __DIR__ . '/../src/Core/Database.php';
require __DIR__ . '/../src/Models/Usuario.php';
require __DIR__ . '/../src/Middleware/Security.php';

use Demo\Core\Database;
use Demo\Models\Usuario;
use Demo\Middleware\Security;

function check(bool $pass,string $message): void {
    if(!$pass)throw new RuntimeException($message);
}
function mustFail(callable $fn,string $label,int $expected=0): void {
    try{$fn();}catch(Throwable $e){
        if($expected!==0&&$e->getCode()!==$expected)throw new RuntimeException('Código inesperado en '.$label.': '.$e->getCode());
        return;
    }
    throw new RuntimeException('Debía fallar: '.$label);
}
$db=new Database([
    'db_host'=>getenv('TEST_DB_HOST')?:'127.0.0.1',
    'db_name'=>getenv('TEST_DB_NAME')?:'controlab_test',
    'db_user'=>getenv('TEST_DB_USER')?:'root',
    'db_pass'=>getenv('TEST_DB_PASS')?:'test_password'
]);
$pdo=$db->connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec(file_get_contents(__DIR__.'/../../database/crear_perfiles_usuarios.sql'));
$pdo->exec('DELETE FROM usuario_perfiles');
$pdo->exec('DELETE FROM usuarios');

$pdo->prepare('INSERT INTO usuarios (email,password_hash,activo) VALUES (?,?,1)')
    ->execute(['admin@example.test',password_hash('AdminPassword123!',PASSWORD_DEFAULT)]);
$admin=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO usuario_perfiles (usuario_id,nombre,rol) VALUES (?,?,?)')
    ->execute([$admin,'Administrador inicial','administrador']);

$model=new Usuario($db);
check($model->findActiveByEmail('admin@example.test')['id']===$admin,'Login inicial no funciona');
$lab=$model->create(['nombre'=>'Laboratorista Uno','email'=>'lab@example.test','rol'=>'laboratorista','clave'=>'ClaveLaboratorio2026!']);
$digit=$model->create(['nombre'=>'Digitador Uno','email'=>'digit@example.test','rol'=>'digitador','clave'=>'ClaveDigitador2026!']);
$office=$model->create(['nombre'=>'Oficina Uno','email'=>'oficina@example.test','rol'=>'administrativo','clave'=>'ClaveOficina2026!']);
check(count($model->all())===4,'Listado no contiene cuatro perfiles');
check(!array_key_exists('password_hash',$model->all()[0]),'Listado filtra hashes');
check(password_verify('ClaveDigitador2026!', $pdo->query("SELECT password_hash FROM usuarios WHERE id=".$digit)->fetchColumn()),'Contraseña no hasheada');
mustFail(fn()=>$model->create(['nombre'=>'Otro','email'=>'digit@example.test','rol'=>'digitador','clave'=>'ClaveDigitador2026!']),'correo duplicado',409);
mustFail(fn()=>$model->update($admin,['nombre'=>'Admin','rol'=>'digitador','activo'=>true]),'degradar al último administrador',409);
mustFail(fn()=>$model->update($admin,['nombre'=>'Admin','rol'=>'administrador','activo'=>false]),'desactivar al último administrador',409);
$model->update($office,['nombre'=>'Administrativa Uno','rol'=>'administrativo','activo'=>false]);
check($model->findActiveById($office)===false,'Cuenta inactiva mantiene acceso');
$model->update($office,['nombre'=>'Administrativa Uno','rol'=>'administrativo','activo'=>true]);
check((string)$model->findActiveById($office)['rol']==='administrativo','Reactivación de oficina falló');

session_start();
Security::configure($db);
$_SESSION['uid']=$digit;
$_SESSION['session_version']=1;
check(Security::auth()['rol']==='digitador','Sesión digitador incorrecta');
mustFail(fn()=>Security::requireRole('administrador'),'digitador no debe administrar',403);
$_SESSION['uid']=$lab;
$_SESSION['session_version']=1;
check(Security::requireRole('laboratorista')['rol']==='laboratorista','Laboratorista no permitido');
$model->resetPassword($lab,'NuevaClaveLaboratorio2026!');
check(Security::sessionUser()===null,'Restablecer contraseña no invalidó sesión');
check($model->findActiveByEmail('lab@example.test')['version_credenciales']==2,'No aumentó versión de credenciales');

$pdo->prepare('INSERT INTO usuarios (email,password_hash,activo) VALUES (?,?,1)')
    ->execute(['viejo@example.test',password_hash('UnaClaveLarga2026!',PASSWORD_DEFAULT)]);
$legacy=(int)$pdo->lastInsertId();
check($model->findActiveByEmail('viejo@example.test')===false,'Cuenta antigua sin rol entra');
$model->update($legacy,['nombre'=>'Usuario recuperado','rol'=>'digitador','activo'=>true]);
check($model->findActiveByEmail('viejo@example.test')['rol']==='digitador','No se pudo asignar perfil a cuenta antigua');

$newAdmin=$model->create(['nombre'=>'Administrador Segundo','email'=>'admin2@example.test','rol'=>'administrador','clave'=>'ClaveAdminSegundo2026!']);
$model->update($admin,['nombre'=>'Admin anterior','rol'=>'administrativo','activo'=>true]);
check($model->findActiveById($admin)['rol']==='administrativo','No se pudo degradar con otro administrador activo');
check($model->findActiveById($newAdmin)['rol']==='administrador','Debe quedar un administrador activo');

echo "OK: MySQL usuarios, perfiles, cambios de rol, alta, baja, último admin, revocación de sesiones y cuentas antiguas\n";
