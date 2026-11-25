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
    private const SERVICIO_ENSAYOS=['gran'=>['S01'],'ll'=>['S03'],'lp'=>['S04'],'att'=>['S03','S04'],'hum'=>['S02'],'sol'=>['S05'],'proctor'=>['S09','S10'],'maxmin'=>['S11','S12'],'cbr'=>['S13'],'dens'=>['S14','S15'],'cal'=>['S16'],'por'=>['S17'],'cubic'=>['S20'],'la'=>['S21'],'fines'=>['S23'],'sand'=>['S22']];
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
        $sid=$field?null:self::id($a,'muestra_id');$pid=$field?self::id($a,'punto_id'):null;
        if($field)$this->one('SELECT id FROM lab_puntos_control WHERE id=?',[$pid]);else $this->one('SELECT id FROM lab_muestras WHERE id=?',[$sid]);
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try {
            $ot=self::id($a,'ot_id');$servicio=self::id($a,'servicio_id');
            $orden=$this->one("SELECT o.id,v.obra_id FROM ot_ordenes o JOIN ot_obra_vinculos v ON v.ot_id=o.id WHERE o.id=? AND o.estado<>'cancelada' FOR UPDATE",[$ot]);
            $line=$this->one('SELECT servicio_nombre FROM ot_servicios WHERE ot_id=? AND servicio_id=?',[$ot,$servicio]);
            $maps=$this->list('SELECT ensayo_codigo FROM servicio_ensayos WHERE servicio_id=?',[$servicio]);
            $allowed=[];
            foreach($maps as $m)$allowed=array_merge($allowed,self::SERVICIO_ENSAYOS[$m['ensayo_codigo']]??[]);
            if(!in_array($code,$allowed,true))throw new InvalidArgumentException('El ensayo no está asociado a este servicio de la OT');
            $subject=$field
                ?$this->one('SELECT m.obra_id,p.ot_id FROM lab_puntos_control p JOIN lab_materiales m ON m.id=p.material_id WHERE p.id=?',[$pid])
                :$this->one('SELECT m.obra_id FROM lab_muestras s JOIN lab_materiales m ON m.id=s.material_id WHERE s.id=?',[$sid]);
            if((int)$subject['obra_id']!==(int)$orden['obra_id']||($field&&(int)$subject['ot_id']!==$ot))throw new InvalidArgumentException('La muestra o punto no corresponde a la OT');
            $ej=$this->insert('INSERT INTO lab_ejecuciones(muestra_id,punto_id,ensayo_codigo,fecha,creado_por) VALUES(?,?,?,?,?)',[$sid,$pid,$code,$date,$actor]);
            $this->insert('INSERT INTO lab_ejecucion_ot(ejecucion_id,ot_id,servicio_id,servicio_nombre) VALUES(?,?,?,?)',[$ej,$ot,$servicio,$line['servicio_nombre']]);
            $id=$this->insert('INSERT INTO lab_resultados(ejecucion_id,version,creado_por) VALUES(?,1,?)',[$ej,$actor]);
            $enc=$this->db->reportCrypto()->encryptBytes($id,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'lab_resultado');
            $q=$pdo->prepare('UPDATE lab_resultados SET datos=? WHERE id=?');$q->execute([$enc,$id]);$pdo->commit();return $id;
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
