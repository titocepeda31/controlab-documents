<?php
declare(strict_types=1);
// Ejecutar DESPUÉS de UsuariosMysqlIntegrationTest.php, en base temporal CI.
require __DIR__.'/../src/Core/Database.php';
require __DIR__.'/../src/Models/CatalogoOperativo.php';
require __DIR__.'/../src/Models/OrdenTrabajo.php';
use Demo\Core\Database;
use Demo\Models\OrdenTrabajo;
use Demo\Models\CatalogoOperativo;

function assertOt(bool $ok,string $label): void {
    if(!$ok)throw new RuntimeException('Falló: '.$label);
}
function expectOtFailure(callable $fn,int $code,string $label): void {
    try{$fn();}catch(Throwable $e){
        if($code!==0&&(int)$e->getCode()!==$code)
            throw new RuntimeException($label.': código '.$e->getCode().' en vez de '.$code.' ('.$e->getMessage().')');
        return;
    }
    throw new RuntimeException('No se bloqueó: '.$label);
}
$db=new Database([
    'db_host'=>getenv('TEST_DB_HOST')?:'127.0.0.1',
    'db_name'=>getenv('TEST_DB_NAME')?:'controlab_test',
    'db_user'=>getenv('TEST_DB_USER')?:'root',
    'db_pass'=>getenv('TEST_DB_PASS')?:'test_password'
]);
$pdo=$db->connection();
$sql=file_get_contents(__DIR__.'/../../database/crear_planificador_ot.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS\s+\w+\s*\(.*?\) ENGINE=InnoDB[^;]*;/s',$sql,$matches);
assertOt(count($matches[0])===3,'SQL debe tener tres tablas');
foreach($matches[0] as $statement)$pdo->exec($statement);

$findUser=$pdo->prepare('SELECT id FROM usuarios WHERE email=?');
$byEmail=static function(string $email) use($findUser): int {
    $findUser->execute([$email]);$id=$findUser->fetchColumn();
    if(!$id)throw new RuntimeException('Falta cuenta de prueba '.$email);
    return (int)$id;
};
$admin=$byEmail('admin2@example.test');
$lab=$byEmail('lab@example.test');
$digit=$byEmail('digit@example.test');
$other=$byEmail('viejo@example.test');
$viewerAdmin=['id'=>$admin,'rol'=>'administrador'];
$viewerLab=['id'=>$lab,'rol'=>'laboratorista'];
$viewerDigit=['id'=>$digit,'rol'=>'digitador'];
$viewerOther=['id'=>$other,'rol'=>'digitador'];
$model=new OrdenTrabajo($db);

$plan=[
 'numero_ot'=>'',
 'cliente'=>'Forestal 3M','obra'=>'Camino de ensayo','destino'=>'Predio Los Aromos',
 'comuna'=>'Talca','actividad'=>'Ensayos de densidad',
 'inicio'=>'2026-11-24T08:00','fin'=>'2026-11-24T12:00',
 'estado'=>'planificada','prioridad'=>'normal','observaciones'=>'Punto de encuentro',
 'personal'=>[$lab,$digit]
];
expectOtFailure(fn()=>OrdenTrabajo::validate([...$plan,'fin'=>'2026-11-24T07:00']),0,'fin anterior');
expectOtFailure(fn()=>OrdenTrabajo::validate([...$plan,'estado'=>'cualquiera']),0,'estado inválido');
expectOtFailure(fn()=>OrdenTrabajo::validate([...$plan,'inicio'=>'2026-02-30T10:00']),0,'fecha inexistente');
expectOtFailure(fn()=>OrdenTrabajo::validate([...$plan,'personal'=>[$lab,$lab]]),0,'personal duplicado');
expectOtFailure(fn()=>OrdenTrabajo::validate([...$plan,'destino'=>'']),0,'destino faltante');
$first=$model->create($plan,$admin);
assertOt($first>0,'guardar OT');
$original=$model->find($first,$viewerAdmin);
assertOt($original['numero_ot']==='OT-2026-'.str_pad((string)$first,5,'0',STR_PAD_LEFT),'numeración automática');
assertOt(count($original['personal'])===2,'equipo completo');
assertOt($model->find($first,$viewerLab)['id']===$first,'el trabajador asignado puede ver OT');
expectOtFailure(fn()=>$model->find($first,$viewerOther),403,'otro trabajador no accede al detalle');
assertOt(count($model->all($viewerDigit,'2026-11-23','2026-11-30'))===1,'el digitador ve su asignación');
assertOt(count($model->all($viewerOther,'2026-11-23','2026-11-30'))===0,'personal ajeno no ve asignaciones');
assertOt(count($model->all($viewerAdmin,'2026-11-23','2026-11-30'))===1,'administrador ve calendario');
expectOtFailure(fn()=>$model->all($viewerAdmin,'2026-01-01','2026-12-31'),0,'límite del período');

$overlap=[...$plan,'inicio'=>'2026-11-24T10:00','fin'=>'2026-11-24T13:00','personal'=>[$lab]];
expectOtFailure(fn()=>$model->create($overlap,$admin),409,'evitar OT superpuesta al mismo laboratorista');
$nonOverlap=[...$plan,'inicio'=>'2026-11-24T12:00','fin'=>'2026-11-24T14:00','personal'=>[$lab]];
$second=$model->create($nonOverlap,$admin);
assertOt($second!==$first,'dos OT contiguas');

$stale=[...$plan,'numero_ot'=>$original['numero_ot'],'version'=>$original['version']+1];
expectOtFailure(fn()=>$model->update($first,$stale,$admin),409,'versión anterior');
expectOtFailure(fn()=>$model->update($first,[...$stale,'version'=> $original['version'],'fin'=>'2026-11-24T13:00'],$admin),409,'editar causa choque');
$model->update($first,[...$plan,'numero_ot'=>$original['numero_ot'],'version'=>$original['version'],'estado'=>'cancelada'],$admin);
$after=$model->find($first,$viewerAdmin);
assertOt($after['version']===$original['version']+1,'incremento versión');
assertOt($after['estado']==='cancelada','cancelación guarda');
$third=$model->create([...$overlap,'inicio'=>'2026-11-24T09:00','fin'=>'2026-11-24T11:00'],$admin);
assertOt($third>0,'franja liberada por OT cancelada');
$events=(int)$pdo->query('SELECT COUNT(*) FROM ot_eventos WHERE ot_id='.$first)->fetchColumn();
assertOt($events===2,'historial mínimo de creación y actualización');
$roster=$model->team();
assertOt(count(array_filter($roster,static fn($x)=>(int)$x['id']===$lab))===1,'laboratorista disponible en selector');
assertOt(count(array_filter($roster,static fn($x)=>(int)$x['id']===$digit))===1,'digitador disponible en selector');
// Nuevos catálogos: una obra hereda empresa, dirección y comuna sin confiar en texto del cliente.
$catalog=new CatalogoOperativo($db);
$workList=$catalog->obras();$workId=(int)$workList[0]['id'];
$staffList=$catalog->personal();$fieldId=(int)$staffList[0]['id'];
$linked=[...$plan,'numero_ot'=>'','cliente'=>'Nombre manipulado','obra'=>'Obra falsa',
 'destino'=>'Dirección inventada','comuna'=>'Comuna falsa',
 'obra_id'=>$workId,'personal'=>[],'personal_terreno_ids'=>[$fieldId],
 'inicio'=>'2026-11-25T08:00','fin'=>'2026-11-25T12:00'];
$linkedId=$model->create($linked,$admin);
$saved=$model->find($linkedId,$viewerAdmin);
assertOt($saved['cliente']==='Constructora Test 3M','Cliente verificado por catálogo');
assertOt($saved['obra']==='Obra Norte','Obra tomada del catálogo');
assertOt($saved['destino']==='Ruta K-33 km 5','Dirección de obra del catálogo');
assertOt($saved['comuna']==='Maule','Comuna de obra del catálogo');
assertOt(abs((float)$saved['latitud']+35.42640)<0.000001,'Latitud de obra en OT');
assertOt(abs((float)$saved['longitud']+71.65542)<0.000001,'Longitud de obra en OT');
assertOt((int)$saved['obra_id']===$workId,'Vínculo de obra');
assertOt(count($saved['personal_terreno'])===1,'Personal sin usuario asignado');
assertOt($saved['personal_terreno'][0]['nombre']==='Chofer de Prueba','Nombre del personal de terreno');
assertOt(count($model->all($viewerOther,'2026-11-25','2026-11-25'))===0,'Trabajador con cuenta ajeno no ve personal del terreno');
expectOtFailure(fn()=>$model->create([...$linked,'inicio'=>'2026-11-25T10:00',
 'fin'=>'2026-11-25T13:00'],$admin),409,'Chofer sin usuario no puede estar en dos OT simultáneas');
$linked2=$model->create([...$linked,'inicio'=>'2026-11-25T12:00',
 'fin'=>'2026-11-25T15:00'],$admin);
assertOt($linked2>0,'Turnos contiguos del personal de terreno son permitidos');
assertOt(count($model->all($viewerAdmin,'2026-11-25','2026-11-25'))===2,'Calendario muestra OT con personal sin cuenta');
$model->update($linkedId,[...$linked,'version'=>$saved['version'],'estado'=>'cancelada'],$admin);
$linked3=$model->create([...$linked,'inicio'=>'2026-11-25T09:00','fin'=>'2026-11-25T11:00'],$admin);
assertOt($linked3>0,'La cancelación libera turno de personal sin cuenta');

echo "OK: MariaDB OT, equipos, destinos, agenda, permisos, choques horarios, versiones y cancelación\n";
