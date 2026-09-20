<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Services\PrivateStorage;use PDO;use RuntimeException;
final class SellerWorkController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 private function order(int $id):array{$s=$this->auth->seller();$q=$this->db->prepare('SELECT * FROM orders WHERE id=? AND seller_id=?');$q->execute([$id,$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);return $o;}
 private function storage():PrivateStorage{$c=require $this->root.'/config/app.php';return new PrivateStorage($c['private_storage']);}
 public function spontaneous(Request $r,array $p):void{
  $o=$this->order((int)$p['id']);try{$rid=(int)$r->input('request_id');$q=$this->db->prepare('SELECT * FROM spontaneous_requests WHERE id=? AND order_id=?');$q->execute([$rid,$o['id']]);$req=$q->fetch();if(!$req)throw new RuntimeException('Anforderung nicht gefunden.');if(time()>strtotime($req['grace_ends_at']))throw new RuntimeException('Die Nachfrist ist abgelaufen.');$f=$this->storage()->storeUploaded($r->files['evidence']??[],'evidence',['image/jpeg','image/png','image/webp'],12*1024*1024);$run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$this->db->prepare("INSERT INTO evidences(order_id,order_run_id,order_component_id,spontaneous_request_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,?,'spontaneous',?,?,?,?,?,NOW(),'{}','pending',NOW())")->execute([$o['id'],$run->fetchColumn(),$req['order_component_id']?:null,$rid,$f['path'],$f['original_name'],$f['mime'],$f['size'],$f['sha256']]);$c=$this->db->prepare('SELECT COUNT(*) FROM evidences WHERE spontaneous_request_id=?');$c->execute([$rid]);if((int)$c->fetchColumn()>=(int)$req['requested_count'])$this->db->prepare("UPDATE spontaneous_requests SET status='uploaded' WHERE id=?")->execute([$rid]);Session::flash('success','Zusatzfoto wurde eingereicht.');}catch(\Throwable $e){Session::flash('error',$e->getMessage());}Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function submitTask(Request $r,array $p):void{
  $o=$this->order((int)$p['id']);$executionId=(int)$p['executionId'];
  $q=$this->db->prepare('SELECT te.*,t.config_json,t.order_component_id FROM task_executions te JOIN tasks t ON t.id=te.task_id WHERE te.id=? AND t.order_id=?');
  $q->execute([$executionId,$o['id']]);$x=$q->fetch();if(!$x)Response::abort(404);
  if($x['submitted_at']){Session::flash('error','Diese Aufgabe wurde bereits eingereicht.');Response::redirect('/konto/auftraege/'.$o['id']);}
  if(time()>strtotime($x['grace_ends_at'])){Session::flash('error','Die Nachfrist ist abgelaufen.');Response::redirect('/konto/auftraege/'.$o['id']);}

  $config=json_decode($x['config_json']?:'{}',true)?:[];$fields=$config['fields']??[];$photos=$config['photos']??[];
  $responses=$r->input('field',[]);if(!is_array($responses))$responses=[];$errors=[];
  foreach($fields as $field){
   $key=(string)($field['key']??'');if($key===''||empty($field['required']))continue;
   if(!isset($responses[$key])||$responses[$key]===''||$responses[$key]===[])$errors[]=(string)($field['label']??$key);
  }
  foreach($photos as $photoIndex=>$photo){
   $count=max(1,(int)($photo['required_count']??1));
   for($n=0;$n<$count;$n++){
    $fileKey='task_photo_'.$photoIndex.'_'.$n;
    if(!isset($r->files[$fileKey])||(($r->files[$fileKey]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)){
     $errors[]='Pflichtfoto: '.(string)($photo['label']??('Foto '.($photoIndex+1)));
    }
   }
  }
  if($errors){Session::flash('error','Pflichtangaben fehlen: '.implode(', ',array_unique($errors)));Response::redirect('/konto/auftraege/'.$o['id']);}

  $this->db->beginTransaction();
  try{
   $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$runId=(int)$run->fetchColumn();
   foreach($photos as $photoIndex=>$photo){
    $count=max(1,(int)($photo['required_count']??1));
    for($n=0;$n<$count;$n++){
     $fileKey='task_photo_'.$photoIndex.'_'.$n;
     $file=$this->storage()->storeUploaded($r->files[$fileKey],'evidence',['image/jpeg','image/png','image/webp'],12*1024*1024);
     $meta=json_encode(['label'=>(string)($photo['label']??'Pflichtfoto'),'position'=>$n+1],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
     $this->db->prepare("INSERT INTO evidences(order_id,order_run_id,order_component_id,task_execution_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,?,'task',?,?,?,?,?,NOW(),?,'pending',NOW())")
      ->execute([$o['id'],$runId,$x['order_component_id']?:null,$executionId,$file['path'],$file['original_name'],$file['mime'],$file['size'],$file['sha256'],$meta]);
    }
   }
   $this->db->prepare("UPDATE task_executions SET response_json=?,submitted_at=NOW(),review_status='pending' WHERE id=?")
    ->execute([json_encode($responses,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$executionId]);
   $this->db->commit();Session::flash('success','Aufgabe wurde vollständig eingereicht.');
  }catch(\Throwable $e){
   if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());
  }
  Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function reportDamage(Request $r,array $p):void{
  $o=$this->order((int)$p['id']);
  try{
   $reason=trim((string)$r->input('reason'));if($reason==='')throw new RuntimeException('Bitte den Grund angeben.');
   $componentId=(int)$r->input('order_component_id');$cq=$this->db->prepare("SELECT * FROM order_components WHERE id=? AND order_id=? AND component_type='physical'");$cq->execute([$componentId,$o['id']]);$component=$cq->fetch();if(!$component)throw new RuntimeException('Bitte den betroffenen physischen Auftragsbestandteil auswählen.');
   $file=$this->storage()->storeUploaded($r->files['evidence']??[],'evidence',['image/jpeg','image/png','image/webp'],12*1024*1024);
   $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$runId=(int)$run->fetchColumn();
   $this->db->beginTransaction();
   $this->db->prepare("INSERT INTO evidences(order_id,order_run_id,order_component_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,'damage_initial',?,?,?,?,?,NOW(),'{}','pending',NOW())")->execute([$o['id'],$runId,$componentId,$file['path'],$file['original_name'],$file['mime'],$file['size'],$file['sha256']]);
   $evidenceId=(int)$this->db->lastInsertId();
   $this->db->prepare("INSERT INTO damage_cases(order_id,order_run_id,order_component_id,reason,initial_evidence_id,status,created_at) VALUES(?,?,?,?,?,'reported',NOW())")->execute([$o['id'],$runId,$componentId,$reason,$evidenceId]);
   $this->db->commit();
   Session::flash('success','Beschädigung für „'.$component['title'].'“ wurde gemeldet. Auftrag und Fristen laufen bis zur Entscheidung weiter.');
  }catch(\Throwable $e){
   if($this->db->inTransaction())$this->db->rollBack();
   Session::flash('error',$e->getMessage());
  }
  Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function damageResponse(Request $r,array $p):void{
  $o=$this->order((int)$p['id']);$reqId=(int)$p['requestId'];$q=$this->db->prepare('SELECT dr.* FROM damage_evidence_requests dr JOIN damage_cases dc ON dc.id=dr.damage_case_id WHERE dr.id=? AND dc.order_id=?');$q->execute([$reqId,$o['id']]);$req=$q->fetch();if(!$req)Response::abort(404);if(time()>strtotime($req['grace_ends_at'])){Session::flash('error','Nachfrist abgelaufen.');Response::redirect('/konto/auftraege/'.$o['id']);}
  $payload=[];if(in_array($req['field_type'],['photo','video'],true)){try{$allowed=$req['field_type']==='video'?['video/mp4','video/webm']:['image/jpeg','image/png','image/webp'];$f=$this->storage()->storeUploaded($r->files['evidence']??[],'evidence',$allowed,80*1024*1024);$run=$this->db->prepare('SELECT order_run_id,order_component_id FROM damage_cases WHERE id=?');$run->execute([$req['damage_case_id']]);$case=$run->fetch();$this->db->prepare("INSERT INTO evidences(order_id,order_run_id,order_component_id,damage_evidence_request_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,?,'damage_followup',?,?,?,?,?,NOW(),'{}','pending',NOW())")->execute([$o['id'],$case['order_run_id'],$case['order_component_id'],$reqId,$f['path'],$f['original_name'],$f['mime'],$f['size'],$f['sha256']]);$payload=['evidence_id'=>(int)$this->db->lastInsertId()];}catch(\Throwable $e){Session::flash('error',$e->getMessage());Response::redirect('/konto/auftraege/'.$o['id']);}}else{$payload=['text'=>trim((string)$r->input('response'))];}
  $this->db->prepare("UPDATE damage_evidence_requests SET response_json=?,submitted_at=NOW(),status='submitted' WHERE id=?")->execute([json_encode($payload,JSON_UNESCAPED_UNICODE),$reqId]);Session::flash('success','Nachforderung wurde eingereicht.');Response::redirect('/konto/auftraege/'.$o['id']);
 }
}