<?php
declare(strict_types=1);
namespace Demo\Models;
use Demo\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use PDO;

final class Laboratorio {
    public function __construct(private Database $db) {}
    private const CAMPOS=[
        'S01'=>[['pasa_5','Pasa 5 mm (%)',0,100],['pasa_008','Pasa 0,080 mm (%)',0,100]],
        'S02'=>[['masa_humeda','Masa húmeda con recipiente (g)',0,1000000],['masa_seca','Masa seca con recipiente (g)',0,1000000],['tara','Masa recipiente (g)',0,1000000]],
        'S03'=>[['ll','LL informado (%)',0,1000]],
        'S04'=>[['lp','LP informado (%)',0,1000]],
        'S09'=>[['dmcs','DMCS informada (g/cm³)',0.01,10],['ho','Humedad óptima informada (%)',0,1000]],
        'S10'=>[['dmcs','DMCS informada (g/cm³)',0.01,10],['ho','Humedad óptima informada (%)',0,1000]],
        'S11'=>[['dmin','Densidad mínima informada (g/cm³)',0.01,10]],
        'S12'=>[['dmax','Densidad máxima informada (g/cm³)',0.01,10]],
        'S13'=>[['cbr','CBR informado (%)',0,1000]],
        'S14'=>[['dch','Densidad húmeda (g/cm³)',0.01,10],['humedad','Humedad (%)',0,1000]],
        'S15'=>[['dch','Densidad húmeda informada (g/cm³)',0.01,10],['humedad','Humedad (%)',0,1000]],
    ];
    public static function campos(): array { return self::CAMPOS; }
    private static function texto(array $a,string $k,int $max): string {
        $v=$a[$k]??null;
        if(!is_string($v)||trim($v)===''||mb_strlen(trim($v))>$max)throw new InvalidArgumentException('Campo inválido: '.$k);
        return trim($v);
    }
    private static function id(array $a,string $k): int {
        $v=filter_var($a[$k]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($v===false)throw new InvalidArgumentException('Identificador inválido: '.$k);
        return $v;
    }
    private static function fecha(array $a): string {
        $v=self::texto($a,'fecha',10);
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
        if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Fecha inválida');
        return $v;
    }
    private function one(string $sql,array $args): array {
        $q=$this->db->connection()->prepare($sql);$q->execute($args);
        $r=$q->fetch();if(!$r)throw new RuntimeException('Registro no encontrado',404);return $r;
    }
    private function list(string $sql,array $args=[]): array {
        $q=$this->db->connection()->prepare($sql);$q->execute($args);return $q->fetchAll();
    }
    private function insert(string $sql,array $args): int {
        $p=$this->db->connection();$q=$p->prepare($sql);$q->execute($args);return (int)$p->lastInsertId();
    }
    private const SERVICIO_ENSAYOS=['gran'=>['S01'],'ll'=>['S03'],'lp'=>['S04'],'att'=>['S03','S04'],'hum'=>['S02'],'sol'=>['S05'],'proctor'=>['S09','S10'],'proctor_normal'=>['S09'],'proctor_modificado'=>['S10'],'maxmin'=>['S11','S12'],'cbr'=>['S13'],'dens'=>['S14','S15'],'dens_nuclear'=>['S14'],'dens_cono'=>['S15'],'cal'=>['S16'],'por'=>['S17'],'cubic'=>['S20'],'la'=>['S21'],'fines'=>['S23'],'sand'=>['S22']];
    // Paquetes: Atterberg y max/min tienen dos tareas; Proctor y densidad tienen una con variantes.
    private const GRUPOS_TAREA=[
        'gran'=>['gran'=>['S01']], 'll'=>['ll'=>['S03']], 'lp'=>['lp'=>['S04']],
        'att'=>['ll'=>['S03'],'lp'=>['S04']], 'hum'=>['hum'=>['S02']], 'sol'=>['sol'=>['S05']],
        'uscs'=>['uscs'=>['C03']], 'proctor'=>['proctor'=>['S09','S10']],
        'proctor_normal'=>['proctor_normal'=>['S09']], 'proctor_modificado'=>['proctor_modificado'=>['S10']],
        'maxmin'=>['dmin'=>['S11'],'dmax'=>['S12']], 'cbr'=>['cbr'=>['S13']],
        'dens'=>['dens'=>['S14','S15']], 'dens_nuclear'=>['dens_nuclear'=>['S14']], 'dens_cono'=>['dens_cono'=>['S15']], 'cal'=>['cal'=>['S16']], 'por'=>['por'=>['S17']],
        'cubic'=>['cubic'=>['S20']], 'la'=>['la'=>['S21']], 'fines'=>['fines'=>['S23']], 'sand'=>['sand'=>['S22']]
    ];
    private const TERRENO=['dens','dens_nuclear','dens_cono','cal','por'];
    private const UNIDADES=['unidad','unidades','punto','puntos','ensayo','ensayos','muestra','muestras','calicata','calicatas','control','controles','densidad','densidades'];
    public static function planTareas(array $servicios): array {
        $plan=[];$avisos=[];
        foreach($servicios as $s){
            $sid=(int)$s['servicio_id'];$qty=(int)$s['cantidad'];$nombre=$s['servicio_nombre'];
            $groups=[];
            foreach($s['ensayos'] as $g)foreach(self::GRUPOS_TAREA[$g]??[] as $key=>$options)$groups[$key]=$options;
            if((isset($groups['proctor'])&&(isset($groups['proctor_normal'])||isset($groups['proctor_modificado'])))||(isset($groups['dens'])&&(isset($groups['dens_nuclear'])||isset($groups['dens_cono'])))){
                $avisos[]=$nombre.': combinación de método genérico y específico; revisar configuración.';continue;
            }
            if(!$groups){$avisos[]=$nombre.': sin ensayos asociados (o servicio no técnico).';continue;}
            $unidad=mb_strtolower(trim($s['unidad']));
            if(!in_array($unidad,self::UNIDADES,true)||$qty<1||$qty>500||count($plan)+$qty*count($groups)>2000){
                $avisos[]=$nombre.': revisar cantidad/unidad comercial antes de generar tareas.';continue;
            }
            foreach($groups as $group=>$options)for($i=1;$i<=$qty;$i++){
                $key=$sid.':'.$group.':'.$i;
                $plan[$key]=['servicio_id'=>$sid,'servicio_nombre'=>$nombre,'grupo'=>$group,'ordinal'=>$i,'area'=>in_array($group,self::TERRENO,true)?'terreno':'laboratorio','alternativas'=>implode(',',$options)];
            }
        }
        return ['plan'=>$plan,'avisos'=>$avisos];
    }
    private function planOT(int $ot): array {
        $rows=$this->list('SELECT servicio_id,servicio_nombre,unidad,cantidad FROM ot_servicios WHERE ot_id=? ORDER BY servicio_id',[$ot]);
        foreach($rows as &$s)$s['ensayos']=array_column($this->list('SELECT ensayo_codigo FROM servicio_ensayos WHERE servicio_id=? ORDER BY ensayo_codigo',[(int)$s['servicio_id']]),'ensayo_codigo');
        unset($s);return self::planTareas($rows);
    }
    public function tareasOT(int $ot): array {
        $this->one('SELECT id FROM ot_ordenes WHERE id=?',[$ot]);
        $plan=$this->planOT($ot);$rows=$this->list('SELECT t.*,e.ensayo_codigo FROM lab_tareas t LEFT JOIN lab_ejecuciones e ON e.id=t.ejecucion_id WHERE t.ot_id=? ORDER BY t.servicio_id,t.grupo,t.ordinal',[$ot]);
        $actual=[];
        foreach($rows as $r)$actual[$r['servicio_id'].':'.$r['grupo'].':'.$r['ordinal']]=$r['alternativas'];
        $desired=array_map(static fn($r)=>$r['alternativas'],$plan['plan']);
        ksort($actual);ksort($desired);
        $legacy=$this->list('SELECT x.servicio_id,COUNT(*) AS cantidad FROM lab_ejecucion_ot x LEFT JOIN lab_tareas t ON t.ejecucion_id=x.ejecucion_id WHERE x.ot_id=? AND t.id IS NULL GROUP BY x.servicio_id',[$ot]);
        $blocked=array_map('intval',array_column($legacy,'servicio_id'));
        if($legacy)$plan['avisos'][]='Hay resultados anteriores sin tarea asignada. Esos servicios se bloquean para evitar duplicados; requieren conciliación.';
        $changed=(bool)$rows&&$actual!==$desired;
        if($changed)$plan['avisos'][]='Los servicios o cantidades cambiaron después de preparar tareas. Se conservan las tareas originales y se bloquea el registro hasta conciliar.';
        return ['tareas'=>$rows,'avisos'=>$plan['avisos'],'cambio'=>$changed,'bloqueados'=>$blocked,'por_preparar'=>!$rows&&count($desired)>0];
    }
    public function prepararTareas(int $ot,int $actor): array {
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $this->one("SELECT o.id FROM ot_ordenes o JOIN ot_obra_vinculos v ON v.ot_id=o.id WHERE o.id=? AND o.estado<>'cancelada' FOR UPDATE",[$ot]);
            $existing=$this->list('SELECT id FROM lab_tareas WHERE ot_id=? LIMIT 1',[$ot]);
            if(!$existing){
                $p=$this->planOT($ot);
                $q=$pdo->prepare('INSERT INTO lab_tareas(ot_id,servicio_id,servicio_nombre,grupo,ordinal,area,alternativas,creado_por) VALUES(?,?,?,?,?,?,?,?)');
                foreach($p['plan'] as $r)$q->execute([$ot,$r['servicio_id'],$r['servicio_nombre'],$r['grupo'],$r['ordinal'],$r['area'],$r['alternativas'],$actor]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return $this->tareasOT($ot);
    }
    public function resumenTareas(array $ids): array {
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $rows=$this->list("SELECT ot_id,area,COUNT(*) AS total,SUM(ejecucion_id IS NOT NULL) AS registrados FROM lab_tareas WHERE ot_id IN ($marks) GROUP BY ot_id,area",$ids);
        $out=[];foreach($rows as $r)$out[$r['ot_id']][$r['area']]=['total'=>(int)$r['total'],'registrados'=>(int)$r['registrados']];return $out;
    }
    public function catalogo(): array {
        return ['ensayos'=>$this->list('SELECT * FROM lab_ensayos_catalogo ORDER BY codigo'),'campos'=>self::CAMPOS,'servicio_ensayos'=>self::SERVICIO_ENSAYOS,'requisitos'=>$this->list('SELECT * FROM lab_informe_requisitos ORDER BY codigo')];
    }
    public function tiposMaterial(): array {
        return $this->list('SELECT id,nombre,activo FROM lab_tipos_material ORDER BY activo DESC,nombre,id');
    }
    public function crearTipoMaterial(array $a,int $actor): int {
        return $this->insert('INSERT INTO lab_tipos_material(nombre,creado_por,actualizado_por) VALUES(?,?,?)',[self::texto($a,'nombre',100),$actor,$actor]);
    }
    public function editarTipoMaterial(int $id,array $a,int $actor): void {
        if(!is_bool($a['activo']??null))throw new InvalidArgumentException('Estado inválido');
        $this->one('SELECT id FROM lab_tipos_material WHERE id=?',[$id]);
        $q=$this->db->connection()->prepare('UPDATE lab_tipos_material SET nombre=?,activo=?,actualizado_por=? WHERE id=?');
        $q->execute([self::texto($a,'nombre',100),(int)$a['activo'],$actor,$id]);
    }
    private static function opcional(array $a,string $key,int $max): string {
        $v=$a[$key]??'';
        if(!is_string($v)||mb_strlen(trim($v))>$max)throw new InvalidArgumentException('Campo inválido: '.$key);
        return trim($v);
    }
    private static function detalleMaterial(array $a): array {
        return [self::id($a,'tipo_id'),self::opcional($a,'identificacion',75),self::opcional($a,'procedencia',180),self::opcional($a,'proveedor',180),self::opcional($a,'observaciones',500)];
    }
    private function guardarDetalle(int $material,array $data,int $actor): void {
        $q=$this->db->connection()->prepare('INSERT INTO lab_material_detalle(material_id,tipo_id,identificacion,procedencia,proveedor,observaciones,creado_por) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$material,...$data,$actor]);
    }
    public function materiales(int $obra): array {
        $this->one('SELECT id FROM obras WHERE id=?',[$obra]);
        return $this->list('SELECT m.id,m.nombre,m.obra_id,d.tipo_id,t.nombre AS tipo,d.identificacion,d.procedencia,d.proveedor,d.observaciones FROM lab_materiales m LEFT JOIN lab_material_detalle d ON d.material_id=m.id LEFT JOIN lab_tipos_material t ON t.id=d.tipo_id WHERE m.obra_id=? ORDER BY m.nombre LIMIT 500',[$obra]);
    }
    public function crearMaterial(array $a,int $actor): int {
        $obra=self::id($a,'obra_id');$data=self::detalleMaterial($a);
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $this->one('SELECT id FROM obras WHERE id=? AND activo=1',[$obra]);
            $tipo=$this->one('SELECT nombre FROM lab_tipos_material WHERE id=? AND activo=1 FOR UPDATE',[$data[0]]);
            $nombre=$tipo['nombre'].($data[1]!==''?' · '.$data[1]:'');
            if(mb_strlen($nombre)>180)throw new InvalidArgumentException('Identificación demasiado larga');
            $id=$this->insert('INSERT INTO lab_materiales(obra_id,nombre,creado_por) VALUES(?,?,?)',[$obra,$nombre,$actor]);
            $this->guardarDetalle($id,$data,$actor);$pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function clasificarMaterial(array $a,int $actor): int {
        $material=self::id($a,'material_id');$data=self::detalleMaterial($a);
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $this->one('SELECT id FROM lab_materiales WHERE id=? FOR UPDATE',[$material]);
            $this->one('SELECT id FROM lab_tipos_material WHERE id=? AND activo=1 FOR UPDATE',[$data[0]]);
            if($this->list('SELECT material_id FROM lab_material_detalle WHERE material_id=?',[$material]))throw new RuntimeException('Material ya clasificado; no se modifica su identidad técnica',409);
            $this->guardarDetalle($material,$data,$actor);$pdo->commit();return $material;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function estudiosMaterial(int $material): array {
        $this->one('SELECT id FROM lab_materiales WHERE id=?',[$material]);
        return $this->list("SELECT e.id,e.codigo,e.origen,e.estado,r.metodo,r.version,r.estado AS referencia_estado FROM lab_estudios e LEFT JOIN lab_referencias r ON r.estudio_id=e.id AND r.estado IN ('borrador','aprobado') WHERE e.material_id=? AND e.estado IN ('borrador','aprobado') ORDER BY e.id DESC,r.version DESC LIMIT 100",[$material]);
    }
    public function sujetos(int $material): array {
        $this->one('SELECT id FROM lab_materiales WHERE id=?',[$material]);
        return ['muestras'=>$this->list('SELECT id,codigo,fecha FROM lab_muestras WHERE material_id=? ORDER BY id DESC LIMIT 500',[$material]),'puntos'=>$this->list('SELECT p.id,p.codigo,p.ot_id,o.numero_ot FROM lab_puntos_control p JOIN ot_ordenes o ON o.id=p.ot_id WHERE p.material_id=? ORDER BY p.id DESC LIMIT 500',[$material])];
    }
    public function crearMuestra(array $a,int $actor): int {
        $m=self::id($a,'material_id');$this->one('SELECT id FROM lab_materiales WHERE id=?',[$m]);
        return $this->insert('INSERT INTO lab_muestras(material_id,codigo,fecha,creado_por) VALUES(?,?,?,?)',[$m,self::texto($a,'codigo',80),self::fecha($a),$actor]);
    }
    public function crearPunto(array $a,int $actor): int {
        $m=self::id($a,'material_id');$ot=self::id($a,'ot_id');
        $this->one("SELECT m.id FROM lab_materiales m JOIN ot_obra_vinculos v ON v.obra_id=m.obra_id JOIN ot_ordenes o ON o.id=v.ot_id WHERE m.id=? AND o.id=? AND o.estado<>'cancelada'",[$m,$ot]);
        return $this->insert('INSERT INTO lab_puntos_control(material_id,ot_id,codigo,creado_por) VALUES(?,?,?,?)',[$m,$ot,self::texto($a,'codigo',80),$actor]);
    }
    public static function calcular(string $code,array $values): array {
        $fields=self::CAMPOS[$code]??null;if(!$fields)throw new InvalidArgumentException('Captura aún no disponible');
        $m=[];
        foreach($fields as [$k,$label,$min,$max]){
            $v=$values[$k]??null;
            if(!is_int($v)&&!is_float($v)&&!(is_string($v)&&preg_match('/^\d+(?:[.,]\d+)?$/D',$v)))throw new InvalidArgumentException('Valor obligatorio o inválido: '.$label);
            $n=(float)str_replace(',','.',(string)$v);
            if(!is_finite($n)||$n<$min||$n>$max)throw new InvalidArgumentException('Valor fuera de rango: '.$label);
            $m[$k]=$n;
        }
        $r=[];
        if($code==='S01'){
            if($m['pasa_008']>$m['pasa_5'])throw new InvalidArgumentException('Pasantes inconsistentes');
            $r=['fraccion_fina_008'=>$m['pasa_008']]; // No equivale a granulometría completa ni SUCS normativo.
        }elseif($code==='S02'){
            if($m['masa_seca']<=$m['tara']||$m['masa_humeda']<$m['masa_seca'])throw new InvalidArgumentException('Masas inconsistentes');
            $r=['humedad'=>100*($m['masa_humeda']-$m['masa_seca'])/($m['masa_seca']-$m['tara'])];
        }elseif(in_array($code,['S14','S15'],true)){
            $r=['dcs'=>$m['dch']/(1+$m['humedad']/100)];
        }else{$r=$m;} // Transcripción de resultados informados, no cálculo normativo.
        return ['mediciones'=>$m,'resultados'=>$r,'alcance'=>'captura_parcial_piloto'];
    }
    public function registrar(array $a,int $actor): int {
        $code=self::texto($a,'ensayo_codigo',8);$date=self::fecha($a);
        $values=$a['valores']??null;if(!is_array($values))throw new InvalidArgumentException('Valores inválidos');
        $payload=self::calcular($code,$values);
        $field=in_array($code,['S14','S15'],true);
        $taskId=self::id($a,'tarea_id');$ot=self::id($a,'ot_id');$material=self::id($a,'material_id');
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try {
            $orden=$this->one("SELECT o.id,v.obra_id FROM ot_ordenes o JOIN ot_obra_vinculos v ON v.ot_id=o.id WHERE o.id=? AND o.estado<>'cancelada' FOR UPDATE",[$ot]);
            $task=$this->one('SELECT * FROM lab_tareas WHERE id=? AND ot_id=? FOR UPDATE',[$taskId,$ot]);
            if($task['ejecucion_id']!==null)throw new RuntimeException('La tarea ya tiene resultado',409);
            $status=$this->tareasOT($ot);
            if($status['cambio']||in_array((int)$task['servicio_id'],$status['bloqueados'],true))throw new RuntimeException('La OT requiere conciliación técnica antes de registrar',409);
            if(!in_array($code,explode(',',$task['alternativas']),true)||($field!==($task['area']==='terreno')))throw new InvalidArgumentException('Método no corresponde a la tarea');
            $m=$this->one('SELECT obra_id FROM lab_materiales WHERE id=?',[$material]);
            if((int)$m['obra_id']!==(int)$orden['obra_id'])throw new InvalidArgumentException('El material no corresponde a la obra de la OT');
            $sid=null;$pid=null;
            if($field){
                // El punto físico se crea al guardar el control, nunca al abrir la OT.
                $point=self::texto($a,'punto_codigo',80);
                $pid=$this->insert('INSERT INTO lab_puntos_control(material_id,ot_id,codigo,creado_por) VALUES(?,?,?,?)',[$material,$ot,$point,$actor]);
            }else{
                $sid=self::id($a,'muestra_id');
                $sample=$this->one('SELECT material_id FROM lab_muestras WHERE id=?',[$sid]);
                if((int)$sample['material_id']!==$material)throw new InvalidArgumentException('La muestra no corresponde al material seleccionado');
            }
            $ej=$this->insert('INSERT INTO lab_ejecuciones(muestra_id,punto_id,ensayo_codigo,fecha,creado_por) VALUES(?,?,?,?,?)',[$sid,$pid,$code,$date,$actor]);
            $this->insert('INSERT INTO lab_ejecucion_ot(ejecucion_id,ot_id,servicio_id,servicio_nombre) VALUES(?,?,?,?)',[$ej,$ot,(int)$task['servicio_id'],$task['servicio_nombre']]);
            $id=$this->insert('INSERT INTO lab_resultados(ejecucion_id,version,creado_por) VALUES(?,1,?)',[$ej,$actor]);
            $enc=$this->db->reportCrypto()->encryptBytes($id,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'lab_resultado');
            $q=$pdo->prepare('UPDATE lab_resultados SET datos=? WHERE id=?');$q->execute([$enc,$id]);
            $q=$pdo->prepare('UPDATE lab_tareas SET ejecucion_id=? WHERE id=? AND ejecucion_id IS NULL');$q->execute([$ej,$taskId]);
            if($q->rowCount()!==1)throw new RuntimeException('La tarea ya fue registrada',409);
            $pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function resultadosOT(int $ot): array {
        $this->one('SELECT id FROM ot_ordenes WHERE id=?',[$ot]);
        $rows=$this->list('SELECT r.id,r.version,r.estado,r.datos,e.ensayo_codigo,e.fecha,c.nombre,COALESCE(m.codigo,p.codigo) AS identificacion,x.servicio_id,x.servicio_nombre FROM lab_ejecucion_ot x JOIN lab_ejecuciones e ON e.id=x.ejecucion_id JOIN lab_resultados r ON r.ejecucion_id=e.id JOIN lab_ensayos_catalogo c ON c.codigo=e.ensayo_codigo LEFT JOIN lab_muestras m ON m.id=e.muestra_id LEFT JOIN lab_puntos_control p ON p.id=e.punto_id WHERE x.ot_id=? ORDER BY r.id DESC LIMIT 100',[$ot]);
        foreach($rows as &$row)$row['datos']=json_decode($this->db->reportCrypto()->decryptBytes((int)$row['id'],(string)$row['datos'],'lab_resultado'),true,512,JSON_THROW_ON_ERROR);
        unset($row);return $rows;
    }
    public function resultados(int $material): array {
        $this->one('SELECT id FROM lab_materiales WHERE id=?',[$material]);
        $rows=$this->list('SELECT r.id,r.version,r.estado,r.datos,e.ensayo_codigo,e.fecha,c.nombre,COALESCE(m.codigo,p.codigo) AS identificacion FROM lab_resultados r JOIN lab_ejecuciones e ON e.id=r.ejecucion_id JOIN lab_ensayos_catalogo c ON c.codigo=e.ensayo_codigo LEFT JOIN lab_muestras m ON m.id=e.muestra_id LEFT JOIN lab_puntos_control p ON p.id=e.punto_id WHERE m.material_id=? OR p.material_id=? ORDER BY r.id DESC LIMIT 100',[$material,$material]);
        foreach($rows as &$row)$row['datos']=json_decode($this->db->reportCrypto()->decryptBytes((int)$row['id'],(string)$row['datos'],'lab_resultado'),true,512,JSON_THROW_ON_ERROR);
        unset($row);return $rows;
    }
}
