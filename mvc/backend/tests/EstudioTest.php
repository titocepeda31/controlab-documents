<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Models/Laboratorio.php';
use Demo\Models\Laboratorio;
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function row(int $id,string $code,array $v,int $m=1):array{return ['id'=>$id,'ensayo_codigo'=>$code,'muestra_id'=>$m,'datos'=>['resultados'=>$v]];}
$p=Laboratorio::referenciaPropia([row(1,'S10',['dmcs'=>2.18,'ho'=>8.5])]);ck($p['metodo']==='proctor'&&$p['valores']['dmcs']===2.18,'Proctor');
ck(!Laboratorio::referenciaPropia([row(1,'S11',['dmin'=>1.6])]),'Incompleto');
$r=Laboratorio::referenciaPropia([row(1,'S11',['dmin'=>1.6]),row(2,'S12',['dmax'=>2.1])]);ck($r['metodo']==='relativa','Relativa');
foreach([[row(1,'S11',['dmin'=>1.6]),row(2,'S12',['dmax'=>2.1],2)],[row(1,'S09',['dmcs'=>2]),row(2,'S10',['dmcs'=>2.1])]] as $bad){try{Laboratorio::referenciaPropia($bad);throw new RuntimeException('Debió rechazar');}catch(InvalidArgumentException $e){}}
echo "OK estudios\n";
