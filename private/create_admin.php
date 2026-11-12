<?php
declare(strict_types=1);
// Ejecutar por SSH/CLI, nunca desde la web.
if (PHP_SAPI !== 'cli') { http_response_code(404);exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
$username=trim($argv[1]??'');
if ($username===''||strlen($username)>100){fwrite(STDERR,"Uso: php private/create_admin.php nombre_usuario\n");exit(1);}
fwrite(STDOUT,"Contraseña (mínimo 12 caracteres; entrada visible en esta consola): ");
$password=rtrim((string)fgets(STDIN),"\r\n");
if (strlen($password)<12){fwrite(STDERR,"Contraseña demasiado corta\n");exit(1);}
$hash=password_hash($password,PASSWORD_DEFAULT);
$stmt=\Controlab\Support::db()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')");
try {$stmt->execute([$username,$hash]);fwrite(STDOUT,"Usuario creado.\n");}
catch(PDOException $e){fwrite(STDERR,"No se pudo crear usuario (¿ya existe?).\n");exit(1);}
