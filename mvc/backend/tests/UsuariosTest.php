<?php
declare(strict_types=1);
require __DIR__ . '/../src/Models/Usuario.php';
use Demo\Models\Usuario;
use InvalidArgumentException;

function shouldFail(callable $action,string $label): void {
    try{$action();}catch(InvalidArgumentException $e){return;}
    throw new RuntimeException('No rechazó '.$label);
}
$validateRole=new ReflectionMethod(Usuario::class,'validateRole');
foreach(['administrador','administrativo','laboratorista','digitador'] as $role){
    if($validateRole->invoke(null,$role)!==$role)throw new RuntimeException('Rol inesperado: '.$role);
}
shouldFail(fn()=>$validateRole->invoke(null,'cliente'),'rol no creado');
shouldFail(fn()=>$validateRole->invoke(null,'digitAlizador'),'rol diferente del normalizado');
shouldFail(fn()=>$validateRole->invoke(null,'administrativo;DROP TABLE usuarios'),'injection de rol');
$validatePassword=new ReflectionMethod(Usuario::class,'validatePassword');
if($validatePassword->invoke(null,'abcdEFGH1234')!=='abcdEFGH1234')
    throw new RuntimeException('No aceptó contraseña válida');
shouldFail(fn()=>$validatePassword->invoke(null,'12345678901'),'contraseña corta');
shouldFail(fn()=>$validatePassword->invoke(null,str_repeat('X',129)),'contraseña excesiva');
shouldFail(fn()=>$validatePassword->invoke(null,"12345678901\n"),'salto de línea');
echo "OK: roles válidos y contraseñas fuertes\n";
