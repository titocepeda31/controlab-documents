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
        $data=$report['datos']??[];
        $lines=['CONTROLAB - INFORME DE DEMOSTRACION','DOCUMENTO DE PRUEBA - NO VALIDO COMO CERTIFICADO','ID: '.(string)$report['id'],'Numero: '.(string)($report['numero_informe']??'')];
        foreach($data as $key=>$value) {
            if(!is_scalar($value))continue;
            $line=(string)$key.': '.preg_replace('/[\\x00-\\x1f]+/u',' ',(string)$value);
            foreach(str_split(iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line)?:'',85) as $part)$lines[]=$part;
        }
        $pages=array_chunk($lines,48);
        $objects=[];$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $kids=[];$next=4;
        foreach($pages as $page){
            $pageId=$next++;$streamId=$next++;$kids[]=$pageId.' 0 R';
            $commands=["BT /F1 10 Tf 45 790 Td 13 TL"];
            foreach($page as $line)$commands[]='('.str_replace(['\\\\','(',')'],['\\\\\\\\','\\\\(','\\\\)'],$line).') Tj T*';
            $commands[]='ET';$stream=implode("\n",$commands)."\n";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId]='<< /Length '.strlen($stream).' >>'."\nstream\n".$stream.'endstream';
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $id=>$obj){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(max(array_keys($objects))+1)."\n0000000000 65535 f \n";
        foreach($objects as $id=>$obj)$pdf.=sprintf('%010d 00000 n ', $offsets[$id])."\n";
        return $pdf."trailer\n<< /Size ".(max(array_keys($objects))+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
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
