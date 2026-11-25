<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Models/Laboratorio.php';
use Demo\Models\Laboratorio;
function check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);}
check(abs(Laboratorio::calcular('S14',['dch'=>'2,210','humedad'=>'3'])['resultados']['dcs']-2.14563106796)<1e-9,'DCS');
check(abs(Laboratorio::calcular('S02',['masa_humeda'=>120,'masa_seca'=>110,'tara'=>10])['resultados']['humedad']-10)<1e-9,'Humedad');
foreach([['S01',['pasa_5'=>10,'pasa_008'=>42]],['S02',['masa_humeda'=>10,'masa_seca'=>10,'tara'=>10]],['S14',['dch'=>2,'humedad'=>-1]],['S28',[]]] as [$c,$v]){
    try{Laboratorio::calcular($c,$v);throw new RuntimeException('Debió rechazar '.$c);}catch(InvalidArgumentException $e){}
}
echo "OK cálculos piloto\n";
