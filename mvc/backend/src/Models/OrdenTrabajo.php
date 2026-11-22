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
        return $data;
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
        $sql="SELECT o.id,o.numero_ot,o.cliente,o.obra,o.destino,o.comuna,o.actividad,o.inicio,o.fin,o.estado,o.prioridad,o.version ".
             "FROM ot_ordenes o WHERE o.inicio < ? AND o.fin > ?";
        if(!self::manager($viewer)){
            $sql.=" AND EXISTS (SELECT 1 FROM ot_asignaciones a WHERE a.ot_id=o.id AND a.usuario_id=?)";
            $params[]=(int)$viewer['id'];
        }
        $sql.=" ORDER BY o.inicio,o.id LIMIT 500";
        $q=$this->db->connection()->prepare($sql);$q->execute($params);
        $items=$q->fetchAll(PDO::FETCH_ASSOC);
        $this->attachTeam($items);
        return $items;
    }

    public function find(int $id,array $viewer): array {
        $q=$this->db->connection()->prepare(
            "SELECT o.id,o.numero_ot,o.cliente,o.obra,o.destino,o.comuna,o.actividad,o.inicio,o.fin,o.estado,o.prioridad,o.observaciones,o.version,o.creado_por,o.actualizado_el ".
            "FROM ot_ordenes o WHERE o.id=? LIMIT 1"
        );
        $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
        if(!$item)throw new RuntimeException('Orden de trabajo no encontrada',404);
        if(!self::manager($viewer)){
            $q=$this->db->connection()->prepare('SELECT 1 FROM ot_asignaciones WHERE ot_id=? AND usuario_id=?');
            $q->execute([$id,(int)$viewer['id']]);
            if(!$q->fetchColumn())throw new RuntimeException('No tienes acceso a esta orden de trabajo',403);
        }
        $items=[$item];$this->attachTeam($items);
        return $items[0];
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
        foreach($items as &$item){
            $item['personal']=$people[(int)$item['id']]??[];
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

    public function create(array $input,int $actor): int {
        $data=self::validate($input);
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare(
                'INSERT INTO ot_ordenes (numero_ot,cliente,obra,destino,comuna,actividad,inicio,fin,estado,prioridad,observaciones,creado_por,actualizado_por) '.
                'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $q->execute([$data['numero_ot']?:null,$data['cliente'],$data['obra'],$data['destino'],$data['comuna'],$data['actividad'],
                $data['inicio'],$data['fin'],$data['estado'],$data['prioridad'],$data['observaciones'],$actor,$actor]);
            $id=(int)$pdo->lastInsertId();
            if($data['numero_ot']===''){
                $number='OT-'.substr($data['inicio'],0,4).'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
                $q=$pdo->prepare('UPDATE ot_ordenes SET numero_ot=? WHERE id=?');
                $q->execute([$number,$id]);
            }
            $this->saveAssignments($pdo,$id,$data['personal'],$data['inicio'],$data['fin'],$data['estado']);
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
        $version=$input['version']??null;
        if(!is_int($version)||$version<1)throw new InvalidArgumentException('Actualiza la OT antes de guardar cambios');
        $pdo=$this->db->connection();
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT numero_ot,version FROM ot_ordenes WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Orden no encontrada',404);
            if($version!==(int)$row['version'])throw new RuntimeException('Otro usuario modificó la OT. Recarga antes de guardar',409);
            $number=$data['numero_ot']?:$row['numero_ot'];
            $this->saveAssignments($pdo,$id,$data['personal'],$data['inicio'],$data['fin'],$data['estado']);
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
