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
    public function catalogo(): array {
        return ['ensayos'=>$this->list('SELECT * FROM lab_ensayos_catalogo ORDER BY codigo'),'campos'=>self::CAMPOS,'requisitos'=>$this->list('SELECT * FROM lab_informe_requisitos ORDER BY codigo')];
    }
    public function materiales(int $obra): array {
        $this->one('SELECT id FROM obras WHERE id=?',[$obra]);
        return $this->list('SELECT id,nombre,obra_id FROM lab_materiales WHERE obra_id=? ORDER BY nombre LIMIT 500',[$obra]);
    }
    public function crearMaterial(array $a,int $actor): int {
        $obra=self::id($a,'obra_id');$this->one('SELECT id FROM obras WHERE id=? AND activo=1',[$obra]);
        return $this->insert('INSERT INTO lab_materiales(obra_id,nombre,creado_por) VALUES(?,?,?)',[$obra,self::texto($a,'nombre',180),$actor]);
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
            $ej=$this->insert('INSERT INTO lab_ejecuciones(muestra_id,punto_id,ensayo_codigo,fecha,creado_por) VALUES(?,?,?,?,?)',[$sid,$pid,$code,$date,$actor]);
            $id=$this->insert('INSERT INTO lab_resultados(ejecucion_id,version,creado_por) VALUES(?,1,?)',[$ej,$actor]);
            $enc=$this->db->reportCrypto()->encryptBytes($id,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'lab_resultado');
            $q=$pdo->prepare('UPDATE lab_resultados SET datos=? WHERE id=?');$q->execute([$enc,$id]);$pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function resultados(int $material): array {
        $this->one('SELECT id FROM lab_materiales WHERE id=?',[$material]);
        $rows=$this->list('SELECT r.id,r.version,r.estado,r.datos,e.ensayo_codigo,e.fecha,c.nombre,COALESCE(m.codigo,p.codigo) AS identificacion FROM lab_resultados r JOIN lab_ejecuciones e ON e.id=r.ejecucion_id JOIN lab_ensayos_catalogo c ON c.codigo=e.ensayo_codigo LEFT JOIN lab_muestras m ON m.id=e.muestra_id LEFT JOIN lab_puntos_control p ON p.id=e.punto_id WHERE m.material_id=? OR p.material_id=? ORDER BY r.id DESC LIMIT 100',[$material,$material]);
        foreach($rows as &$row)$row['datos']=json_decode($this->db->reportCrypto()->decryptBytes((int)$row['id'],(string)$row['datos'],'lab_resultado'),true,512,JSON_THROW_ON_ERROR);
        unset($row);return $rows;
    }
}
