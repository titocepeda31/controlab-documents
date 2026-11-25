<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Models/Laboratorio.php';
use Demo\Models\Laboratorio;
function verify(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$s=['servicio_id'=>1,'servicio_nombre'=>'Densidad','unidad'=>'unidad','cantidad'=>12,'ensayos'=>['dens']];
$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===12,'12 controles');foreach($p['plan'] as $r)verify($r['area']==='terreno'&&$r['alternativas']==='S14,S15','Una variante por tarea');
$s['ensayos']=['att','ll','lp','proctor'];$s['cantidad']=1;$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===3,'Atterberg sin duplicados + Proctor');
$s['unidad']='viaje';$p=Laboratorio::planTareas([$s]);verify(!$p['plan']&&$p['avisos'],'Unidad no técnica');
echo "OK planificación\n";
