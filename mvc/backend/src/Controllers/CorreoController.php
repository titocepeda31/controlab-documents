<?php
declare(strict_types=1);
namespace Demo\Controllers;

use Demo\Core\Database;
use Demo\Core\Http;
use Demo\Middleware\Security;
use Demo\Models\Informe;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use InvalidArgumentException;

/** Demo only: authenticated Gmail SMTP with generated provisional PDF attachments. */
final class CorreoController {
    public function __construct(private Database $db, private array $config, private string $private) {}
    private function settings(): array {
        $s=$this->config['smtp_demo']??[];
        if (!is_array($s)||empty($s['user'])||empty($s['password'])||empty($s['allowed_recipient'])) {
            throw new RuntimeException('SMTP de demostración sin configurar',422);
        }
        return $s;
    }
    private function pdf(array $report): string {
        $d=$report['datos']??[];
        $v=static fn($k)=>is_scalar($d[$k]??null)?(string)$d[$k]:'';
        $c=[];
        $t=static function($x,$y,$s,$size=8,$bold=false,$max=110)use(&$c){
            $s=preg_replace('/[\x00-\x1f]+/u',' ',(string)$s)??'';
            $s=mb_substr($s,0,$max);
            $s=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s)?:'';
            $s=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);
            $c[]=sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET',$bold?'F2':'F1',$size,$x,842-$y,$s);
        };
        $rect=static function($x,$y,$w,$h)use(&$c){$c[]=sprintf('%.2F %.2F %.2F %.2F re S',$x,842-$y-$h,$w,$h);};
        $line=static function($x,$y,$xx,$yy)use(&$c){$c[]=sprintf('%.2F %.2F m %.2F %.2F l S',$x,842-$y,$xx,842-$yy);};
        $c[]='0.45 w';
        $t(245,27,'CONTROLAB',17,true);
        $t(421,20,'Ref. Código F-7,8-S1',7,true);
        $t(421,30,'Fecha: 21-08-2025',7);
        $t(421,40,'Nº Edición: 04 / Pág 1 de 1',7);
        $rect(18,43,190,38);
        $t(22,54,'Nº de Informe: '.($v('nInforme')?:($report['numero_informe']??'')),9,true,40);
        $t(22,65,'Fecha de registro: '.$v('fechaRegistro'));
        $t(22,76,'Nº de Muestra: '.$v('muestra'));
        $t(226,91,'INFORME DE ENSAYO',13,true);
        $t(145,104,'DEMOSTRACIÓN - NO VÁLIDO COMO CERTIFICADO',9,true);
        $rect(18,111,559,64);
        $t(21,121,'DATOS DEL CLIENTE',9,true);$line(18,125,577,125);
        $t(21,135,'Nombre: '.($v('cliente')?:$v('mandante')),8,false,100);
        $t(21,145,'Dirección: '.$v('direccion').'    Contacto: '.$v('contacto').'    Teléfono: '.$v('telefono'),7);
        $line(18,149,577,149);$t(21,159,'DATOS DE LA OBRA',9,true);$line(18,163,577,163);
        $t(21,172,'Obra: '.($v('obra')?:$v('predio')).' / '.$v('camino').' / '.$v('sector'),8);
        $t(185,187,'DETERMINACIÓN DE LA GRANULOMETRÍA',9,true);
        $t(100,197,'Ref. Normativa: Método 8.102.1 Dic. 2003 M.C.V.8',7);
        $rect(18,202,559,155);
        foreach([210,302,394,486] as $x)$line($x,202,$x,357);
        $t(21,212,'Fecha de ensayo');$t(213,212,$v('fechaEnsayo'));
        $t(21,223,'Identificación de la muestra');$t(213,223,$v('muestra'),7,false,20);
        $line(18,227,577,227);
        $t(65,237,'ASTM / NCh',8,true);
        foreach([213,305,397] as $x)$t($x,237,'% QUE PASA',8,true);
        $t(489,237,'Especificaciones',7,true);
        $line(18,241,577,241);
        $tamices=['3" / 80 mm','2½" / 63 mm','2" / 50 mm','1½" / 40 mm','1" / 25 mm','3/4" / 20 mm','3/8" / 10 mm','Nº 4 / 5 mm','Nº 10 / 2,0 mm','Nº 40 / 0,5 mm','Nº 200 / 0,080 mm'];
        foreach($tamices as $i=>$label){$y=250+$i*10;$t(65,$y,$label);$t(235,$y,$v('pasa_'.($i+2)));}
        $y=359;
        $section=static function($title,$norm,$rows)use(&$y,$t,$rect,$line,$v){
            $t(21,$y+10,$title,8,true,130);$t(21,$y+19,$norm,7,false,140);$y+=20;
            $h=count($rows)*9+3;$rect(18,$y,559,$h);
            foreach([210,302,394,486] as $x)$line($x,$y,$x,$y+$h);
            foreach($rows as $i=>$row){$t(21,$y+8+$i*9,$row[0],7,false,54);$t(213,$y+8+$i*9,$v($row[1]),7,false,24);}
            $y+=$h+2;
        };
        $section('DETERMINACIÓN DEL LÍMITE LÍQUIDO','NCh1517/1.Of1979',[['Límite líquido (%)','ll'],['Tipo de acanalador','acanalador'],['Método de ensayo','metodoLL']]);
        $section('DETERMINACIÓN DEL LÍMITE PLÁSTICO','NCh1517/2.Of1979',[['Límite plástico (%)','lp'],['Índice de plasticidad IP (%)','ip']]);
        $section('DETERMINACIÓN RELACIÓN HUMEDAD/DENSIDAD','NCh1534/II.Of1979',[['D.M.C.H. (Gr/Cm3)','dch'],['Humedad óptima (%)','humedadProctor'],['D.M.C.S. (Gr/Cm3)','dcs'],['Método de ensayo','metodo'],['Material retenido 20 mm','materialRetenido']]);
        $section('DETERMINACIÓN DE LA RAZÓN DE SOPORTE','NCh 1852.Of81',[['C.B.R. (%)','cbr']]);
        $section('DETERMINACIÓN DE HUMEDAD','NCh 1515 Of 1979',[['Humedad (%)','humedad']]);
        $section('DETERMINACIÓN DE LA CUBICIDAD DE PARTÍCULAS','Mét. 8.202.6 Jun. 2009 M.C.V.8',[['Chancado (%)','chancado']]);
        $section('RESISTENCIA AL DESGASTE - MÁQUINA DE LOS ÁNGELES','NCh1369-2010',[['Desgaste (%)','desgaste']]);
        $t(21,$y+9,'ANTECEDENTES DE LA(S) MUESTRA(S)',8,true);
        $t(21,$y+19,'Responsable: '.$v('responsable').'   Fecha muestreo: '.$v('fechaMuestreo'),7);
        $t(21,$y+29,'Estructura: '.$v('estructura').'   AASHTO: '.$v('aashto').'   USCS: '.$v('usc'),7);
        $t(21,$y+39,'Observaciones: '.$v('observaciones'),7,false,145);
        $t(21,812,'Documento de prueba sin firma. No corresponde a un certificado oficial.',8,true);
        $t(220,827,'- Fin del informe -',8);
        $stream=implode("\n",$c)."\n";
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'<< /Type /Pages /Kids [5 0 R] /Count 1 >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',5=>'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents 6 0 R >>',6=>'<< /Length '.strlen($stream).' >>'."\nstream\n".$stream.'endstream'];
        $pdf="%PDF-1.4\n";$offsets=[];
        foreach($objects as $id=>$obj){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 7\n0000000000 65535 f \n";
        foreach($offsets as $offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";
        return $pdf."trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
    public function send(Request $request,Response $response): Response {
        $user=Security::requireRole('administrador','administrativo');Security::csrf($request);
        $input=Http::jsonBody($request);$settings=$this->settings();
        $to=strtolower(trim((string)($input['destinatario']??'')));
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!hash_equals(strtolower(trim((string)$settings['allowed_recipient'])),$to))
            throw new InvalidArgumentException('Para la demostración solo se permite el destinatario autorizado');
        $ids=$input['informes']??[];
        if(!is_array($ids)||count($ids)<1||count($ids)>10||count(array_unique($ids))!==count($ids))
            throw new InvalidArgumentException('Selecciona entre 1 y 10 informes distintos');
        $files=[];$model=new Informe($this->db);
        foreach($ids as $id){
            if(!is_int($id)||$id<1)throw new InvalidArgumentException('ID de informe inválido');
            $report=$model->find($id);
            $files[]=['name'=>'informe-demo-'.$id.'.pdf','content'=>$this->pdf($report)];
        }
        if(!function_exists('curl_init'))throw new RuntimeException('La extensión cURL no está disponible',422);
        $from=trim((string)$settings['user']);$boundary='CL'.bin2hex(random_bytes(12));
        $subject='Informes de demostración Controlab';
        $body="Este mensaje contiene documentos de demostración. No son certificados oficiales.";
        $mime="From: ". $from."\r\nTo: ".$to."\r\nSubject: ".$subject."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"".$boundary."\"\r\n\r\n";
        $mime.="--".$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body."\r\n";
        foreach($files as $file)$mime.="--".$boundary."\r\nContent-Type: application/pdf; name=\"".$file['name']."\"\r\nContent-Disposition: attachment; filename=\"".$file['name']."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($file['content']),76,"\r\n");
        $mime.="--".$boundary."--\r\n";
        $port=(int)($settings['port']??587);$host=(string)($settings['host']??'smtp.gmail.com');
        if($host!=='smtp.gmail.com'||$port!==587)throw new RuntimeException('El piloto solo permite Gmail STARTTLS',422);
        $ch=curl_init('smtp://smtp.gmail.com:587');
        $offset=0;$length=strlen($mime);
        curl_setopt_array($ch,[CURLOPT_USERNAME=>$from,CURLOPT_PASSWORD=>(string)$settings['password'],CURLOPT_USE_SSL=>CURLUSESSL_ALL,CURLOPT_MAIL_FROM=>$from,CURLOPT_MAIL_RCPT=>[$to],CURLOPT_UPLOAD=>true,CURLOPT_READFUNCTION=>static function($ch,$fd,$size)use(&$offset,$mime,$length){$chunk=substr($mime,$offset,$size);$offset+=strlen($chunk);return $chunk;},CURLOPT_INFILESIZE=>$length,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $result=curl_exec($ch);$err=curl_error($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($result===false||$code>=400){error_log('[CONTROLAB SMTP] delivery failed code='.$code.' error='.$err);throw new RuntimeException('No se pudo completar el envío SMTP',502);}
        $log=['fecha'=>gmdate('c'),'usuario_id'=>(int)$user['id'],'destinatario'=>$to,'informes'=>$ids,'estado'=>'aceptado_por_smtp'];
        $path=$this->private.'/correo_demo_envios.jsonl';
        if(file_put_contents($path,json_encode($log,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX)===false)error_log('[CONTROLAB SMTP] No se pudo registrar historial');
        return Http::json($response,['ok'=>true,'adjuntos'=>count($files),'estado'=>'aceptado_por_smtp']);
    }
}
