<?php
declare(strict_types=1);
// Ejecución aislada en MariaDB de CI (nunca en la base de producción).
require __DIR__.'/../src/Core/Database.php';
require __DIR__.'/../src/Models/CatalogoOperativo.php';
require __DIR__.'/../src/Models/MapaObras.php';
use Demo\Core\Database;
use Demo\Models\CatalogoOperativo;
use Demo\Models\MapaObras;

function checkCatalog(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
}
function badCatalog(callable $fn,string $message,int $expected=0): void {
    try{$fn();}catch(Throwable $e){
        if($expected!==0&&(int)$e->getCode()!==$expected)
            throw new RuntimeException('Código inesperado: '.$message.' = '.$e->getCode());
        return;
    }
    throw new RuntimeException('Debió fallar: '.$message);
}
$db=new Database([
 'db_host'=>getenv('TEST_DB_HOST')?:'127.0.0.1',
 'db_name'=>getenv('TEST_DB_NAME')?:'controlab_test',
 'db_user'=>getenv('TEST_DB_USER')?:'root',
 'db_pass'=>getenv('TEST_DB_PASS')?:'test_password',
]);
$pdo=$db->connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS empresas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 razon_social VARCHAR(160) NOT NULL,
 rut VARCHAR(30) DEFAULT NULL,
 contacto VARCHAR(160) DEFAULT NULL,
 email VARCHAR(180) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$sql=file_get_contents(__DIR__.'/../../database/crear_obras_y_personal_terreno.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS\s+\w+\s*\(.*?\) ENGINE=InnoDB[^;]*;/s',$sql,$match);
checkCatalog(count($match[0])===4,'La migración debe crear cuatro tablas');
foreach($match[0] as $statement)$pdo->exec($statement);
$geoSQL=file_get_contents(__DIR__.'/../../database/crear_ubicaciones_osm.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS\\s+\\w+\\s*\\(.*?\\) ENGINE=InnoDB[^;]*;/s',$geoSQL,$geoTables);
checkCatalog(count($geoTables[0])===3,'Migración geográfica con tres tablas');
foreach($geoTables[0] as $statement)$pdo->exec($statement);
$pdo->exec('INSERT INTO geo_nominatim_limite (id,ultimo_contacto_ms) VALUES (1,0) ON DUPLICATE KEY UPDATE id=id');


$usersBefore=(int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$model=new CatalogoOperativo($db);
$company=$model->createEmpresa(['razon_social'=>'Constructora Test 3M']);
checkCatalog($company>0,'Crear empresa');
badCatalog(fn()=>$model->createEmpresa(['razon_social'=>'Constructora Test 3M']),'empresa duplicada',409);
$work=$model->saveObra(['empresa_id'=>$company,'nombre'=>'Obra Norte','direccion'=>'Ruta K-33 km 4','comuna'=>'Talca']);
checkCatalog($work>0,'Crear obra');
$record=$model->obraById($work);
checkCatalog((int)$record['empresa_id']===$company,'Obra asociada a empresa');
checkCatalog($record['direccion']==='Ruta K-33 km 4','Dirección propia');
badCatalog(fn()=>$model->saveObra(['empresa_id'=>$company,'nombre'=>'Obra Norte','direccion'=>'Otra calle','comuna'=>'Talca']),'obra duplicada',409);
$model->saveObra(['empresa_id'=>$company,'nombre'=>'Obra Norte','direccion'=>'Ruta K-33 km 5','comuna'=>'Maule','activo'=>true],$work);
checkCatalog($model->obraById($work)['comuna']==='Maule','Actualizar comuna');
$mapa=new MapaObras($db);
$mapa->guardar($work,['latitud'=>-35.42640,'longitud'=>-71.65542,'fuente'=>'mapa_manual']);
$located=$model->obraById($work);
checkCatalog(abs((float)$located['latitud']+35.42640)<0.000001,'Coordenada latitud guardada');
checkCatalog(abs((float)$located['longitud']+71.65542)<0.000001,'Coordenada longitud guardada');
$model->saveObra(['empresa_id'=>$company,'nombre'=>'Obra Norte',
 'direccion'=>'Ruta K-33 km 6','comuna'=>'Maule','activo'=>true],$work);
checkCatalog($model->obraById($work)['latitud']===null,
 'Cambiar dirección invalida coordenada anterior para evitar ruta errónea');
$model->saveObra(['empresa_id'=>$company,'nombre'=>'Obra Norte',
 'direccion'=>'Ruta K-33 km 5','comuna'=>'Maule','activo'=>true],$work);
$mapa->guardar($work,['latitud'=>-35.42640,'longitud'=>-71.65542,'fuente'=>'mapa_manual']);

badCatalog(fn()=>$mapa->guardar($work,['latitud'=>90.1,'longitud'=>-71.65542]),'Coordenada fuera de Chile');
badCatalog(fn()=>$mapa->guardar(999999,['latitud'=>-35.42640,'longitud'=>-71.65542]),'No crear marcador en obra inexistente',404);

$person=$model->savePersonal(['nombre'=>'Chofer de Prueba','cargo'=>'Conductor','observaciones'=>'Móvil de muestras']);
checkCatalog($person>0,'Crear personal sin usuario');
$model->savePersonal(['nombre'=>'Chofer de Prueba','cargo'=>'Muestreador','observaciones'=>'Turno A','activo'=>false],$person);
$staff=$model->personal();
checkCatalog(count(array_filter($staff,static fn($x)=>(int)$x['id']===$person&&(int)$x['activo']===0))===1,'Desactivar trabajador sin borrarlo');
$model->savePersonal(['nombre'=>'Chofer de Prueba','cargo'=>'Conductor','observaciones'=>'Turno B','activo'=>true],$person);
checkCatalog(count($model->obras())===1,'Lista de obras');
checkCatalog(count($model->empresas())===1,'Lista de empresas');
checkCatalog((int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn()===$usersBefore,'Crear trabajador no genera cuenta de usuario');
echo "OK: empresas, obras por empresa, dirección, comuna y personal sin login\n";
