<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Models/Laboratorio.php';
use Demo\Models\Laboratorio;
function verify(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$s=['servicio_id'=>1,'servicio_nombre'=>'Densidad','unidad'=>'unidad','cantidad'=>12,'ensayos'=>['dens']];
$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===12,'12 controles');foreach($p['plan'] as $r)verify($r['area']==='terreno'&&$r['alternativas']==='S14,S15','Una variante por tarea');
$s['ensayos']=['att','ll','lp','proctor'];$s['cantidad']=1;$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===3,'Atterberg sin duplicados + Proctor');
$s['unidad']='viaje';$p=Laboratorio::planTareas([$s]);verify(!$p['plan']&&$p['avisos'],'Unidad no técnica');
$s['unidad']='punto';$s['cantidad']=13;$s['ensayos']=['dens_nuclear'];$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===13,'13 nucleares');foreach($p['plan'] as $r)verify($r['alternativas']==='S14'&&$r['area']==='terreno','Método nuclear fijo');
$s['ensayos']=['dens_cono'];$p=Laboratorio::planTareas([$s]);foreach($p['plan'] as $r)verify($r['alternativas']==='S15'&&$r['area']==='terreno','Cono fijo');
$s['cantidad']=1;$s['unidad']='ensayo';$s['ensayos']=['proctor_normal','proctor_modificado'];$p=Laboratorio::planTareas([$s]);verify(count($p['plan'])===2,'Dos métodos contratados');
$s['ensayos']=['dens','dens_nuclear'];$p=Laboratorio::planTareas([$s]);verify(!$p['plan']&&$p['avisos'],'Conflicto genérico y específico');
echo "OK planificación\n";
