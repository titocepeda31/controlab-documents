<?php
declare(strict_types=1);
namespace Demo\Models;

use Demo\Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Ubicaciones de obras y geocodificación explícita, con límites de Nominatim. */
final class MapaObras {
    public function __construct(private Database $db) {}

    public static function validarCoordenadas(mixed $lat,mixed $lon): array {
        if(!(is_int($lat)||is_float($lat)||is_string($lat)&&is_numeric($lat))||
           !(is_int($lon)||is_float($lon)||is_string($lon)&&is_numeric($lon)))
            throw new InvalidArgumentException('Selecciona un punto válido en el mapa');
        $a=(float)$lat;$b=(float)$lon;
        if(!is_finite($a)||!is_finite($b)||$a < -90||$a>90||$b < -180||$b>180)
            throw new InvalidArgumentException('Coordenadas fuera de rango');
        // Restringido a Chile continental / territorio nacional usual para el piloto.
        if($a < -56.5||$a > -17.4||$b < -76.5||$b > -66.0)
            throw new InvalidArgumentException('El punto debe estar dentro del área continental de Chile');
        return [round($a,7),round($b,7)];
    }

    public function guardar(int $obraId,array $data): void {
        if($obraId<1)throw new InvalidArgumentException('Obra inválida');
        [$lat,$lon]=self::validarCoordenadas($data['latitud']??null,$data['longitud']??null);
        $source=$data['fuente']??'mapa_manual';
        if(!in_array($source,['busqueda','mapa_manual'],true))
            throw new InvalidArgumentException('Fuente de ubicación inválida');
        $pdo=$this->db->connection();
        $q=$pdo->prepare('SELECT id FROM obras WHERE id=?');$q->execute([$obraId]);
        if(!$q->fetchColumn())throw new RuntimeException('Obra no encontrada',404);
        $q=$pdo->prepare('INSERT INTO obra_ubicaciones (obra_id,latitud,longitud,fuente) VALUES (?,?,?,?) '.
            'ON DUPLICATE KEY UPDATE latitud=VALUES(latitud),longitud=VALUES(longitud),fuente=VALUES(fuente)');
        $q->execute([$obraId,$lat,$lon,$source]);
    }

    public function buscar(string $query): array {
        $query=trim(preg_replace('/\s+/u',' ',$query)??'');
        if(mb_strlen($query)<5||mb_strlen($query)>160)
            throw new InvalidArgumentException('Escribe al menos 5 caracteres y máximo 160 para buscar');
        // Llamada a Nominatim sólo tras pulsar Buscar, nunca autocompletar remoto.
        $key=hash('sha256',mb_strtolower($query));
        $pdo=$this->db->connection();
        $cache=$pdo->prepare('SELECT respuesta,actualizado_el FROM geo_busquedas_cache WHERE clave=?');
        $cache->execute([$key]);$row=$cache->fetch(PDO::FETCH_ASSOC);
        if($row && strtotime($row['actualizado_el'])>time()-30*86400){
            $data=json_decode((string)$row['respuesta'],true);
            if(is_array($data))return $data;
        }
        // El bloqueo es GLOBAL en MySQL, incluso con varias sesiones web.
        $locked=(int)$pdo->query("SELECT GET_LOCK('controlab_nominatim_v1',8)")->fetchColumn();
        if($locked!==1)throw new RuntimeException('Búsqueda ocupada. Intenta nuevamente.',503);
        try {
            $cache->execute([$key]);$row=$cache->fetch(PDO::FETCH_ASSOC);
            if($row && strtotime($row['actualizado_el'])>time()-30*86400){
                $data=json_decode((string)$row['respuesta'],true);
                if(is_array($data))return $data;
            }
            $last=(int)$pdo->query('SELECT ultimo_contacto_ms FROM geo_nominatim_limite WHERE id=1')->fetchColumn();
            $now=(int)floor(microtime(true)*1000);
            $wait=1100-($now-$last);
            if($wait>0)usleep(min(2000000,$wait*1000));
            // Marcar el momento incluso si el proveedor está temporalmente caído.
            $stamp=(int)floor(microtime(true)*1000);
            $pdo->prepare('UPDATE geo_nominatim_limite SET ultimo_contacto_ms=? WHERE id=1')->execute([$stamp]);
            if(!function_exists('curl_init'))
                throw new RuntimeException('La búsqueda de direcciones requiere cURL habilitado en PHP',503);
            $params=http_build_query([
                'q'=>$query.', Chile',
                'format'=>'jsonv2',
                'countrycodes'=>'cl',
                'limit'=>5,
                'addressdetails'=>1
            ],'','&',PHP_QUERY_RFC3986);
            $url='https://nominatim.openstreetmap.org/search?'.$params;
            $curl=curl_init($url);
            curl_setopt_array($curl,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CONNECTTIMEOUT=>4,
                CURLOPT_TIMEOUT=>8,
                CURLOPT_USERAGENT=>'ControlabPilot/1.0 (+https://maderasyferreteriasmuena.cl/demo/)',
                CURLOPT_HTTPHEADER=>['Accept: application/json','Accept-Language: es-CL,es;q=0.9'],
                CURLOPT_SSL_VERIFYPEER=>true
            ]);
            $response=curl_exec($curl);
            $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if(!is_string($response)||$status!==200)
                throw new RuntimeException('El buscador de mapas no respondió. Prueba más tarde o indica el punto manualmente.',503);
            $json=json_decode($response,true);
            if(!is_array($json))throw new RuntimeException('Resultado cartográfico inválido',503);
            $results=[];
            foreach(array_slice($json,0,5) as $item){
                if(!is_array($item))continue;
                try{[$lat,$lon]=self::validarCoordenadas($item['lat']??null,$item['lon']??null);}catch(InvalidArgumentException){continue;}
                $label=mb_substr((string)($item['display_name']??''),0,220);
                if($label==='')continue;
                $results[]=['nombre'=>$label,'latitud'=>$lat,'longitud'=>$lon];
            }
            $cacheSave=$pdo->prepare('INSERT INTO geo_busquedas_cache (clave,respuesta) VALUES (?,?) '.
                'ON DUPLICATE KEY UPDATE respuesta=VALUES(respuesta),actualizado_el=CURRENT_TIMESTAMP');
            $cacheSave->execute([$key,json_encode($results,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            return $results;
        }finally{
            $pdo->query("SELECT RELEASE_LOCK('controlab_nominatim_v1')");
        }
    }
}
