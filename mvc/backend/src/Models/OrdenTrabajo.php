<?php
declare(strict_types=1);
namespace Demo\Models;

use DateTimeImmutable;
use DateTimeZone;
use Demo\Core\Database;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Planificación de trabajos en hora local de Chile. Solo personal interno.
 * Un miembro no puede estar asignado a dos OT activas que se solapen.
 */
final class OrdenTrabajo {
    public const ESTADOS=['planificada','en_curso','finalizada','cancelada'];
    public const PRIORIDADES=['normal','urgente'];
    public function __construct(private Database $db) {}

    private static function manager(array $user): bool {
        return in_array($user['rol']??'', ['administrador','administrativo'],true);
    }

    private static function date(string $input): string {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$input)) throw new InvalidArgumentException('La fecha debe tener formato YYYY-MM-DD');
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$input,new DateTimeZone('America/Santiago'));
        if(!$dt||$dt->format('Y-m-d')!==$input)throw new InvalidArgumentException('Fecha inválida');
        return $input;
    }

    private static function time(mixed $value): string {
        if(!is_string($value))throw new InvalidArgumentException('Indica fecha y hora');
        $input=str_replace('T',' ',trim($value));
        if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',$input))throw new InvalidArgumentException('Formato de fecha y hora inválido');
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$input,new DateTimeZone('America/Santiago'));
        if(!$dt||$dt->format('Y-m-d H:i')!==$input)throw new InvalidArgumentException('Fecha y hora inválidas');
        return $input.':00';
    }

    private static function field(array $input,string $key,int $max,bool $required=true): string {
        $value=$input[$key]??'';
        if(!is_string($value))throw new InvalidArgumentException('El campo '.$key.' debe ser texto');
        $value=trim($value);
        if(($required&&$value==='')||mb_strlen($value)>$max)throw new InvalidArgumentException('Revisa el campo '.$key.' (máximo '.$max.' caracteres)');
        return $value;
    }

    /** Reglas de negocio también aplicadas en el servidor, nunca confiamos en el HTML. */
    public static function validate(array $input): array {
        $data=[];
        foreach(['cliente'=>160,'obra'=>180,'destino'=>240,'actividad'=>200] as $key=>$limit)$data[$key]=self::field($input,$key,$limit);
        $data['comuna']=self::field($input,'comuna',120,false);
        $data['observaciones']=self::field($input,'observaciones',5000,false);
        $data['numero_ot']=self::field($input,'numero_ot',40,false);
        if($data['numero_ot']!==''&&!preg_match('/^[a-zA-Z0-9._\/-]{3,40}$/D',$data['numero_ot']))
            throw new InvalidArgumentException('Número OT: usa letras, números, puntos, guiones o / (3 a 40 caracteres)');
        $data['inicio']=self::time($input['inicio']??null);
        $data['fin']=self::time($input['fin']??null);
        $start=new DateTimeImmutable($data['inicio'],new DateTimeZone('America/Santiago'));
        $end=new DateTimeImmutable($data['fin'],new DateTimeZone('America/Santiago'));
        if($end<=$start)throw new InvalidArgumentException('La fecha final debe ser posterior al inicio');
        if($end->getTimestamp()-$start->getTimestamp()>31*86400)
            throw new InvalidArgumentException('Una OT puede durar como máximo 31 días');
        $data['estado']=$input['estado']??'planificada';
        if(!is_string($data['estado'])||!in_array($data['estado'],self::ESTADOS,true))throw new InvalidArgumentException('Estado de OT inválido');
        $data['prioridad']=$input['prioridad']??'normal';
        if(!is_string($data['prioridad'])||!in_array($data['prioridad'],self::PRIORIDADES,true))throw new InvalidArgumentException('Prioridad inválida');
        $personal=$input['personal']??[];
        if(!is_array($personal)||count($personal)>30)throw new InvalidArgumentException('Equipo inválido (máximo 30 personas)');
        $ids=[];
        foreach($personal as $value){
            if((!is_int($value)&&!(is_string($value)&&ctype_digit($value)))||(int)$value<1)
                throw new InvalidArgumentException('Identificador de trabajador inválido');
            $ids[]=(int)$value;
        }
        if(count(array_unique($ids))!==count($ids))throw new InvalidArgumentException('No repitas trabajadores en una OT');
        sort($ids,SORT_NUMERIC);
        $data['personal']=$ids;
        $crew=$input['personal_terreno_ids']??[];
        if(!is_array($crew)||count($crew)>40)throw new InvalidArgumentException('Equipo de terreno inválido (máximo 40 personas)');
        $crewIds=[];
        foreach($crew as $value){
            if((!is_int($value)&&!(is_string($value)&&ctype_digit($value)))||(int)$value<1)
                throw new InvalidArgumentException('Identificador de personal de terreno inválido');
            $crewIds[]=(int)$value;
        }
        if(count(array_unique($crewIds))!==count($crewIds))
            throw new InvalidArgumentException('El mismo trabajador de terreno no puede repetirse');
        sort($crewIds,SORT_NUMERIC);
        $data['personal_terreno_ids']=$crewIds;
        $obraId=$input['obra_id']??null;
        if($obraId===null||$obraId==='')$data['obra_id']=null;
        elseif((is_int($obraId)||is_string($obraId)&&ctype_digit($obraId))&&(int)$obraId>0)$data['obra_id']=(int)$obraId;
        else throw new InvalidArgumentException('Obra seleccionada inválida');
        $vehicle=$input['vehiculo_id']??null;
        if($vehicle===null||$vehicle==='')$data['vehiculo_id']=null;
        elseif((is_int($vehicle)||is_string($vehicle)&&ctype_digit($vehicle))&&(int)$vehicle>0)$data['vehiculo_id']=(int)$vehicle;
        else throw new InvalidArgumentException('Vehículo inválido');
        $data['cotizacion_id']=empty($input['cotizacion_id'])?null:filter_var($input['cotizacion_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($data['cotizacion_id']===false)throw new InvalidArgumentException('Cotización inválida');
        $data['servicios']=null;
        if(array_key_exists('servicios',$input)){
            if(!is_array($input['servicios'])||count($input['servicios'])>60)
                throw new InvalidArgumentException('Lista de servicios inválida (máximo 60)');
            $list=[];$seen=[];
            foreach($input['servicios'] as $line){
                if(!is_array($line))throw new InvalidArgumentException('Servicio inválido');
                $sid=$line['servicio_id']??null;$qty=$line['cantidad']??null;
                if(!(is_int($sid)||is_string($sid)&&ctype_digit($sid))||(int)$sid<1)
                    throw new InvalidArgumentException('Selecciona un servicio válido');
                if(!(is_int($qty)||is_string($qty)&&ctype_digit($qty))||(int)$qty<1||(int)$qty>100000)
                    throw new InvalidArgumentException('La cantidad debe estar entre 1 y 100000');
                $sid=(int)$sid;
                if(isset($seen[$sid]))throw new InvalidArgumentException('No repitas el mismo servicio en la OT');
                $seen[$sid]=true;$list[]=['servicio_id'=>$sid,'cantidad'=>(int)$qty];
            }
            $data['servicios']=$list;
        }
        return $data;
    }

    private function applyObraCatalog(array &$data): void {
        if($data['obra_id']===null)return;
        $obra=(new CatalogoOperativo($this->db))->obraById($data['obra_id']);
        $status=$this->db->connection()->prepare('SELECT activo FROM empresas WHERE id=?');
        $status->execute([$obra['empresa_id']]);
        if(!(int)$status->fetchColumn())throw new InvalidArgumentException('La empresa está inactiva');
        if(!(int)$obra['activo'])throw new InvalidArgumentException('La obra está inactiva');
        if((int)$obra['activo']!==1)throw new InvalidArgumentException('La obra está inactiva. Elige otra.');
        $data['cliente']=$obra['empresa'];
        $data['obra']=$obra['nombre'];
        $data['destino']=$obra['direccion'];
        $data['comuna']=$obra['comuna'];
    }

    public function vehicles(): array {
        return $this->db->connection()->query('SELECT id,patente,descripcion,activo FROM catalogo_vehiculos ORDER BY patente')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addVehicle(array $input): int {
        $plate=strtoupper(trim((string)($input['patente']??'')));
        if(!preg_match('/^[A-Z0-9-]{4,12}$/D',$plate))throw new InvalidArgumentException('Patente inválida (4 a 12 caracteres)');
        $description=self::field($input,'descripcion',120,false);
        $q=$this->db->connection()->prepare('INSERT INTO catalogo_vehiculos (patente,descripcion) VALUES (?,?)');
        try{$q->execute([$plate,$description]);}catch(PDOException $e){if($e->getCode()==='23000')throw new RuntimeException('Esta patente ya existe',409);throw $e;}
        return (int)$this->db->connection()->lastInsertId();
    }
    private function saveVehicle(PDO $pdo,int $otId,?int $vehicleId): void {
        $q=$pdo->prepare('DELETE FROM ot_vehiculos WHERE ot_id=?');$q->execute([$otId]);
        if($vehicleId===null)return;
        $q=$pdo->prepare('SELECT id FROM catalogo_vehiculos WHERE id=? AND activo=1');
        $q->execute([$vehicleId]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Camioneta inexistente o inactiva');
        $q=$pdo->prepare('INSERT INTO ot_vehiculos (ot_id,vehiculo_id) VALUES (?,?)');$q->execute([$otId,$vehicleId]);
    }
    private function saveObraLink(PDO $pdo,int $otId,?int $obraId): void {
        if($obraId===null){
            $q=$pdo->prepare('DELETE FROM ot_obra_vinculos WHERE ot_id=?');
            $q->execute([$otId]);return;
        }
        $q=$pdo->prepare('INSERT INTO ot_obra_vinculos (ot_id,obra_id) VALUES (?,?) ON DUPLICATE KEY UPDATE obra_id=VALUES(obra_id)');
        $q->execute([$otId,$obraId]);
    }

    public function fieldTeam(): array {
        return $this->db->connection()->query(
            'SELECT id,nombre,cargo,activo FROM personal_terreno WHERE activo=1 ORDER BY nombre,id LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function team(): array {
        return $this->db->connection()->query(
            "SELECT u.id,p.nombre,p.rol FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id ".
            "WHERE u.activo=1 AND p.rol IN ('laboratorista','digitador') ORDER BY p.nombre,u.id LIMIT 300"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function all(array $viewer,string $from,string $until): array {
        $from=self::date($from);
        $until=self::date($until);
        $start=new DateTimeImmutable($from.' 00:00:00');
        $last=new DateTimeImmutable($until.' 00:00:00');
        if($last<$start||$last->diff($start)->days>100)throw new InvalidArgumentException('Consulta un período de hasta 100 días');
        $max=$last->modify('+1 day')->format('Y-m-d').' 00:00:00';
        $params=[$max,$from.' 00:00:00'];
        $sql="SELECT o.id,o.numero_ot,o.cotizacion_id,(SELECT numero FROM cotizaciones WHERE id=o.cotizacion_id) AS numero_cotizacion,o.cliente,o.obra,o.destino,o.comuna,o.actividad,o.inicio,o.fin,o.estado,o.prioridad,o.version,v.obra_id,g.latitud,g.longitud,ov.vehiculo_id,cv.patente ".
             "FROM ot_ordenes o LEFT JOIN ot_obra_vinculos v ON v.ot_id=o.id LEFT JOIN obra_ubicaciones g ON g.obra_id=v.obra_id LEFT JOIN ot_vehiculos ov ON ov.ot_id=o.id LEFT JOIN catalogo_vehiculos cv ON cv.id=ov.vehiculo_id WHERE o.inicio < ? AND o.fin > ?";
        if(!self::manager($viewer)){
            $sql.=" AND EXISTS (SELECT 1 FROM ot_asignaciones a WHERE a.ot_id=o.id AND a.usuario_id=?)";
            $params[]=(int)$viewer['id'];
        }
        $sql.=" ORDER BY o.inicio,o.id LIMIT 500";
        $q=$this->db->connection()->prepare($sql);$q->execute($params);
        $items=$q->fetchAll(PDO::FETCH_ASSOC);
        $this->attachTeam($items);
        $this->attachServicios($items);
        return $items;
    }

    public function find(int $id,array $viewer): array {
        $q=$this->db->connection()->prepare(
            "SELECT o.id,o.numero_ot,o.cotizacion_id,(SELECT numero FROM cotizaciones WHERE id=o.cotizacion_id) AS numero_cotizacion,o.cliente,o.obra,o.destino,o.comuna,o.actividad,o.inicio,o.fin,o.estado,o.prioridad,o.observaciones,o.version,o.creado_por,o.actualizado_el,v.obra_id,g.latitud,g.longitud,ov.vehiculo_id,cv.patente ".
            "FROM ot_ordenes o LEFT JOIN ot_obra_vinculos v ON v.ot_id=o.id LEFT JOIN obra_ubicaciones g ON g.obra_id=v.obra_id LEFT JOIN ot_vehiculos ov ON ov.ot_id=o.id LEFT JOIN catalogo_vehiculos cv ON cv.id=ov.vehiculo_id WHERE o.id=? LIMIT 1"
        );
        $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item)throw new RuntimeException('Orden de trabajo no encontrada',404);
        if(!self::manager($viewer)){
            $q=$this->db->connection()->prepare('SELECT 1 FROM ot_asignaciones WHERE ot_id=? AND usuario_id=?');
            $q->execute([$id,(int)$viewer['id']]);
            if(!$q->fetchColumn())throw new RuntimeException('No tienes acceso a esta orden de trabajo',403);
        }
        $items=[$item];$this->attachTeam($items);$this->attachServicios($items);
        return $items[0];
    }


    private function saveServicios(PDO $pdo,int $otId,?array $items): void {
        if($items===null)return; // Compatibilidad con clientes antiguos que no envían servicios.
        $ids=array_column($items,'servicio_id');$catalog=[];
        if($ids){
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT id,nombre,unidad,activo FROM catalogo_servicios WHERE id IN ($marks) ORDER BY id FOR UPDATE");
            $q->execute($ids);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$catalog[(int)$row['id']]=$row;
            foreach($ids as $sid){
                if(!isset($catalog[$sid])||(int)$catalog[$sid]['activo']!==1)
                    throw new InvalidArgumentException('Hay servicios inexistentes o inactivos en la OT');
            }
        }
        $q=$pdo->prepare('DELETE FROM ot_servicios WHERE ot_id=?');$q->execute([$otId]);
        $q=$pdo->prepare('INSERT INTO ot_servicios (ot_id,servicio_id,cantidad,servicio_nombre,unidad) VALUES (?,?,?,?,?)');
        foreach($items as $item){
            $s=$catalog[$item['servicio_id']];
            $q->execute([$otId,$item['servicio_id'],$item['cantidad'],$s['nombre'],$s['unidad']]);
        }
    }

    private function attachServicios(array &$items): void {
        if(!$items)return;
        $ids=array_map(static fn($item)=>(int)$item['id'],$items);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->db->connection()->prepare("SELECT s.ot_id,s.servicio_id,s.servicio_nombre AS nombre,s.unidad,s.cantidad,(SELECT GROUP_CONCAT(m.ensayo_codigo ORDER BY m.ensayo_codigo) FROM servicio_ensayos m WHERE m.servicio_id=s.servicio_id) AS ensayo_codigos FROM ot_servicios s WHERE s.ot_id IN ($marks) ORDER BY s.servicio_nombre");
        $q->execute($ids);$group=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $line){
            $line['servicio_id']=(int)$line['servicio_id'];$line['cantidad']=(int)$line['cantidad'];
            $line['ensayos']=empty($line['ensayo_codigos'])?[]:explode(',',$line['ensayo_codigos']);unset($line['ensayo_codigos']);
            $group[(int)$line['ot_id']][]=$line;
        }
        foreach($items as &$item)$item['servicios']=$group[(int)$item['id']]??[];
        unset($item);
    }

    private function attachTeam(array &$items): void {
        if(!$items)return;
        $ids=array_map(static fn($o)=>(int)$o['id'],$items);
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->db->connection()->prepare(
            "SELECT a.ot_id,a.usuario_id,p.nombre,p.rol FROM ot_asignaciones a ".
            "JOIN usuario_perfiles p ON p.usuario_id=a.usuario_id WHERE a.ot_id IN ($ph) ".
            "ORDER BY p.nombre"
        );
        $q->execute($ids);
        $people=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $people[(int)$row['ot_id']][]=['id'=>(int)$row['usuario_id'],'nombre'=>$row['nombre'],'rol'=>$row['rol']];
        }
        $q=$this->db->connection()->prepare(
            "SELECT a.ot_id,a.personal_id,p.nombre,p.cargo FROM ot_personal_terreno a ".
            "JOIN personal_terreno p ON p.id=a.personal_id WHERE a.ot_id IN ($ph) ORDER BY p.nombre"
        );
        $q->execute($ids);
        $fieldWorkers=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $fieldWorkers[(int)$row['ot_id']][]=['id'=>(int)$row['personal_id'],'nombre'=>$row['nombre'],'cargo'=>$row['cargo']];
        }
        foreach($items as &$item){
            $item['personal']=$people[(int)$item['id']]??[];
            $item['personal_terreno']=$fieldWorkers[(int)$item['id']]??[];
            $item['obra_id']=$item['obra_id']===null?null:(int)$item['obra_id'];
            $item['version']=(int)$item['version'];
        }
        unset($item);
    }

    /** Bloquea las filas de cada trabajador ANTES de consultar los choques de horario:
     * dos solicitudes simultáneas sobre el mismo trabajador se serializan.
     */
    private function validateTeam(PDO $pdo,array $ids,int $otId,string $start,string $end,string $state): void {
        if(!$ids)return;
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $lock=$pdo->prepare(
            "SELECT u.id,p.rol,u.activo FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id ".
            "WHERE u.id IN ($ph) ORDER BY u.id FOR UPDATE"
        );
        $lock->execute($ids);
        $valid=$lock->fetchAll(PDO::FETCH_ASSOC);
        if(count($valid)!==count($ids))throw new InvalidArgumentException('Hay trabajadores inexistentes o sin perfil');
        foreach($valid as $person){
            if((int)$person['activo']!==1||!in_array($person['rol'],['laboratorista','digitador'],true))
                throw new InvalidArgumentException('Solo puedes asignar laboratoristas o digitadores activos');
        }
        if(!in_array($state,['planificada','en_curso'],true))return;
        $sql="SELECT o.numero_ot,p.nombre FROM ot_asignaciones a ".
             "JOIN ot_ordenes o ON o.id=a.ot_id JOIN usuario_perfiles p ON p.usuario_id=a.usuario_id ".
             "WHERE a.usuario_id IN ($ph) AND o.id<>? AND o.estado IN ('planificada','en_curso') ".
             "AND o.inicio<? AND o.fin>? LIMIT 5";
        $q=$pdo->prepare($sql);
        $q->execute([...$ids,$otId,$end,$start]);
        $collision=$q->fetchAll(PDO::FETCH_ASSOC);
        if($collision){
            $messages=array_map(static fn($x)=>$x['nombre'].' (OT '.$x['numero_ot'].')',$collision);
            throw new RuntimeException('Conflicto de horario: '.implode(', ',$messages),409);
        }
    }

    /** Bloqueos también cubren el equipo antiguo en una edición, para liberar turnos sin carreras. */
    private function saveAssignments(PDO $pdo,int $id,array $newIds,string $start,string $end,string $state): void {
        $q=$pdo->prepare('SELECT usuario_id FROM ot_asignaciones WHERE ot_id=?');
        $q->execute([$id]);$old=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $ids=array_values(array_unique([...$old,...$newIds]));sort($ids,SORT_NUMERIC);
        // Los usuarios antiguos podrían estar inactivos: bloquearlos, pero validar solo los NUEVOS.
        if($ids){
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $lock=$pdo->prepare("SELECT id FROM usuarios WHERE id IN ($ph) ORDER BY id FOR UPDATE");
            $lock->execute($ids);$lock->fetchAll();
        }
        $this->validateTeam($pdo,$newIds,$id,$start,$end,$state);
        $q=$pdo->prepare('DELETE FROM ot_asignaciones WHERE ot_id=?');$q->execute([$id]);
        if($newIds){
            $q=$pdo->prepare('INSERT INTO ot_asignaciones (ot_id,usuario_id) VALUES (?,?)');
            foreach($newIds as $person)$q->execute([$id,$person]);
        }
    }

    private function saveFieldAssignments(PDO $pdo,int $id,array $people,string $start,string $end,string $state): void {
        $q=$pdo->prepare('SELECT personal_id FROM ot_personal_terreno WHERE ot_id=?');
        $q->execute([$id]);$old=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $all=array_values(array_unique([...$old,...$people]));sort($all,SORT_NUMERIC);
        if($all){
            $marks=implode(',',array_fill(0,count($all),'?'));
            $q=$pdo->prepare("SELECT id FROM personal_terreno WHERE id IN ($marks) ORDER BY id FOR UPDATE");
            $q->execute($all);$q->fetchAll();
        }
        if($people){
            $marks=implode(',',array_fill(0,count($people),'?'));
            $q=$pdo->prepare("SELECT id,nombre,activo FROM personal_terreno WHERE id IN ($marks)");
            $q->execute($people);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==count($people))throw new InvalidArgumentException('Alguna persona de terreno no existe');
            foreach($rows as $person){
                if((int)$person['activo']!==1)throw new InvalidArgumentException('No puedes asignar personal de terreno inactivo');
            }
            if(in_array($state,['planificada','en_curso'],true)){
                $q=$pdo->prepare("SELECT p.nombre,o.numero_ot FROM ot_personal_terreno a ".
                    "JOIN ot_ordenes o ON o.id=a.ot_id JOIN personal_terreno p ON p.id=a.personal_id ".
                    "WHERE a.personal_id IN ($marks) AND a.ot_id<>? ".
                    "AND o.estado IN ('planificada','en_curso') AND o.inicio<? AND o.fin>? LIMIT 5");
                $q->execute([...$people,$id,$end,$start]);$conflicts=$q->fetchAll(PDO::FETCH_ASSOC);
                if($conflicts){
                    $names=array_map(static fn($x)=>$x['nombre'].' (OT '.$x['numero_ot'].')',$conflicts);
                    throw new RuntimeException('Conflicto de horario del personal de terreno: '.implode(', ',$names),409);
                }
            }
        }
        $q=$pdo->prepare('DELETE FROM ot_personal_terreno WHERE ot_id=?');$q->execute([$id]);
        if($people){
            $q=$pdo->prepare('INSERT INTO ot_personal_terreno (ot_id,personal_id) VALUES (?,?)');
            foreach($people as $person)$q->execute([$id,$person]);
        }
    }

    private function validateCotizacion(PDO $pdo,array $data,int $otId): void {
        $id=$data['cotizacion_id'];if($id===null)return;
        $q=$pdo->prepare('SELECT obra_id,estado FROM cotizaciones WHERE id=? FOR UPDATE');$q->execute([$id]);$quote=$q->fetch(PDO::FETCH_ASSOC);
        if(!$quote||$quote['estado']!=='aprobada'||(int)$quote['obra_id']!==$data['obra_id'])throw new InvalidArgumentException('Selecciona una cotización aprobada de la misma obra');
        if(!$data['servicios'])throw new InvalidArgumentException('Indica servicios cotizados');
        $q=$pdo->prepare("SELECT s.cantidad,COALESCE((SELECT SUM(t.cantidad) FROM ot_servicios t JOIN ot_ordenes x ON x.id=t.ot_id WHERE x.cotizacion_id=s.cotizacion_id AND t.servicio_id=s.servicio_id AND x.id<>? AND x.estado<>'cancelada'),0) AS asignada FROM cotizacion_servicios s WHERE s.cotizacion_id=? AND s.servicio_id=?");
        foreach($data['servicios'] as $line){$q->execute([$otId,$id,$line['servicio_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||($data['estado']!=='cancelada'&&$row['cantidad']!==null&&$line['cantidad']+(int)$row['asignada']>(int)$row['cantidad']))throw new InvalidArgumentException('Servicio no cotizado o cantidad superior al saldo');}
    }
    public function create(array $input,int $actor): int {
        $data=self::validate($input);
        if($data['cotizacion_id']===null)throw new InvalidArgumentException('Selecciona una cotización aprobada');
        $this->applyObraCatalog($data);
        $pdo=$this->db->connection();
        // READ COMMITTED: la consulta de choques ve lo que confirmó otra OT
        // mientras esperábamos el bloqueo del mismo trabajador.
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare(
                'INSERT INTO ot_ordenes (numero_ot,cliente,obra,destino,comuna,actividad,inicio,fin,estado,prioridad,observaciones,creado_por,actualizado_por) '.
                'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $q->execute([$data['numero_ot']?:null,$data['cliente'],$data['obra'],$data['destino'],$data['comuna'],$data['actividad'],
                $data['inicio'],$data['fin'],$data['estado'],$data['prioridad'],$data['observaciones'],$actor,$actor]);
            $id=(int)$pdo->lastInsertId();
            $this->validateCotizacion($pdo,$data,$id);
            $q=$pdo->prepare('UPDATE ot_ordenes SET cotizacion_id=? WHERE id=?');$q->execute([$data['cotizacion_id'],$id]);
            if($data['numero_ot']===''){
                $number='OT-'.substr($data['inicio'],0,4).'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
                $q=$pdo->prepare('UPDATE ot_ordenes SET numero_ot=? WHERE id=?');
                $q->execute([$number,$id]);
            }
            $this->saveAssignments($pdo,$id,$data['personal'],$data['inicio'],$data['fin'],$data['estado']);
            $this->saveFieldAssignments($pdo,$id,$data['personal_terreno_ids'],$data['inicio'],$data['fin'],$data['estado']);
            $this->saveObraLink($pdo,$id,$data['obra_id']);
            $this->saveVehicle($pdo,$id,$data['vehiculo_id']);
            $this->saveServicios($pdo,$id,$data['servicios']);
            $q=$pdo->prepare("INSERT INTO ot_eventos (ot_id,usuario_id,accion,resumen) VALUES (?,?,'crear',?)");
            $q->execute([$id,$actor,'OT creada con '.count($data['personal']).' persona(s)']);
            $pdo->commit();return $id;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($e instanceof PDOException&&$e->getCode()==='23000')
                throw new RuntimeException('El número de OT ya existe, elige otro',409);
            throw $e;
        }
    }

    public function update(int $id,array $input,int $actor): void {
        if($id<1)throw new InvalidArgumentException('ID de OT inválido');
        $data=self::validate($input);
        $this->applyObraCatalog($data);
        $version=$input['version']??null;
        if(!is_int($version)||$version<1)throw new InvalidArgumentException('Actualiza la OT antes de guardar cambios');
        $pdo=$this->db->connection();
        // READ COMMITTED: la consulta de choques ve lo que confirmó otra OT
        // mientras esperábamos el bloqueo del mismo trabajador.
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT numero_ot,version,cotizacion_id FROM ot_ordenes WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Orden no encontrada',404);
            if($version!==(int)$row['version'])throw new RuntimeException('Otro usuario modificó la OT. Recarga antes de guardar',409);
            $data['cotizacion_id']=$row['cotizacion_id']===null?null:(int)$row['cotizacion_id'];
            $this->validateCotizacion($pdo,$data,$id);
            $this->saveServicios($pdo,$id,$data['servicios']);
            $number=$data['numero_ot']?:$row['numero_ot'];
            $this->saveAssignments($pdo,$id,$data['personal'],$data['inicio'],$data['fin'],$data['estado']);
            $this->saveFieldAssignments($pdo,$id,$data['personal_terreno_ids'],$data['inicio'],$data['fin'],$data['estado']);
            $this->saveObraLink($pdo,$id,$data['obra_id']);
            $this->saveVehicle($pdo,$id,$data['vehiculo_id']);
            $q=$pdo->prepare(
                'UPDATE ot_ordenes SET numero_ot=?,cliente=?,obra=?,destino=?,comuna=?,actividad=?,inicio=?,fin=?,estado=?,prioridad=?,observaciones=?,actualizado_por=?,version=version+1 WHERE id=?'
            );
            $q->execute([$number,$data['cliente'],$data['obra'],$data['destino'],$data['comuna'],$data['actividad'],
                $data['inicio'],$data['fin'],$data['estado'],$data['prioridad'],$data['observaciones'],$actor,$id]);
            $q=$pdo->prepare("INSERT INTO ot_eventos (ot_id,usuario_id,accion,resumen) VALUES (?,?,'actualizar',?)");
            $q->execute([$id,$actor,'Planificación, destino, estado o equipo modificado']);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($e instanceof PDOException&&$e->getCode()==='23000')
                throw new RuntimeException('El número de OT ya existe, elige otro',409);
            throw $e;
        }
    }
}
