<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Services\OrderService;use PDO;use DateTimeImmutable;
final class AdminWorkController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function spontaneous(Request $r,array $p):void{
  $orderId=(int)$p['id'];
  try{
   $componentId=$this->resolveComponent($orderId,(int)$r->input('order_component_id'),true);
   $count=max(1,(int)$r->input('count',1));$deadline=new DateTimeImmutable((string)$r->input('deadline'));$grace=$deadline->modify('+1 hour');
   $this->db->prepare("INSERT INTO spontaneous_requests(order_id,order_component_id,requested_count,motif,description,deadline,grace_ends_at,status,created_at) VALUES(?,?,?,?,?,?,?,'requested',NOW())")
    ->execute([$orderId,$componentId,$count,trim((string)$r->input('motif')),trim((string)$r->input('description')),$deadline->format('Y-m-d H:i:s'),$grace->format('Y-m-d H:i:s')]);
   $rid=(int)$this->db->lastInsertId();$o=$this->db->prepare('SELECT seller_id FROM orders WHERE id=?');$o->execute([$orderId]);$sid=(int)$o->fetchColumn();
   $this->db->prepare("INSERT INTO notifications(seller_id,dedupe_key,type,title,message,url,created_at) VALUES(?,?,'spontaneous','Zusätzliche Fotoanforderung',?,?,NOW())")
    ->execute([$sid,'spontaneous:'.$rid.':created','Zusätzliche Fotoanforderung: '.$count.' Foto(s) bis '.$deadline->format('d.m.Y H:i'),'/konto/auftraege/'.$orderId]);
   $this->systemChat($orderId,'Zusätzliche Fotoanforderung wurde erstellt.');Session::flash('success','Spontane Fotoanforderung erstellt.');
  }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 public function addTask(Request $r,array $p):void{
  $orderId=(int)$p['id'];
  try{
   $componentId=$this->resolveComponent($orderId,(int)$r->input('order_component_id'),false);
   $templateId=(int)$r->input('template_id');$t=$this->db->prepare('SELECT * FROM task_templates WHERE id=? AND is_active=1');$t->execute([$templateId]);$tpl=$t->fetch();if(!$tpl)throw new \RuntimeException('Aufgabenvorlage nicht gefunden.');
   $due=new DateTimeImmutable((string)$r->input('due_at'));$config=json_encode(['fields'=>json_decode($tpl['fields_json']?:'[]',true),'photos'=>json_decode($tpl['photos_json']?:'[]',true),'violation'=>json_decode($tpl['violation_json']?:'[]',true)],JSON_UNESCAPED_UNICODE);
   $this->db->prepare("INSERT INTO tasks(order_id,order_component_id,task_template_id,title,description,schedule_type,config_json,created_at) VALUES(?,?,?,?,?,'once',?,NOW())")->execute([$orderId,$componentId,$templateId,$tpl['title'],$tpl['description'],$config]);$task=(int)$this->db->lastInsertId();
   $this->db->prepare("INSERT INTO task_executions(task_id,due_at,grace_ends_at,review_status,created_at) VALUES(?,?,?,'open',NOW())")->execute([$task,$due->format('Y-m-d H:i:s'),$due->modify('+1 hour')->format('Y-m-d H:i:s')]);
   $this->systemChat($orderId,'Eine Zusatzaufgabe wurde hinzugefügt: '.$tpl['title']);Session::flash('success','Zusatzaufgabe angelegt.');
  }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 public function reviewTask(Request $r,array $p):void{$id=(int)$p['executionId'];$decision=(string)$r->input('decision');$q=$this->db->prepare('SELECT te.*,t.order_id,t.order_component_id FROM task_executions te JOIN tasks t ON t.id=te.task_id WHERE te.id=? AND t.order_id=?');$q->execute([$id,(int)$p['id']]);$x=$q->fetch();if(!$x)Response::abort(404);if($decision==='accepted')$this->db->prepare("UPDATE task_executions SET review_status='accepted' WHERE id=?")->execute([$id]);else{$this->db->prepare("UPDATE task_executions SET review_status='rejected' WHERE id=?")->execute([$id]);$this->ensureViolation((int)$p['id'],'task_not_completed','task_execution',$id,'Zusatzaufgabe wurde als nicht ausreichend bewertet.',$x['order_component_id']?(int)$x['order_component_id']:null);}Session::flash('success','Aufgabe geprüft.');Response::redirect('/admin/auftraege/'.$p['id']);}
 public function requestDamageEvidence(Request $r,array $p):void{$case=(int)$p['caseId'];$q=$this->db->prepare('SELECT * FROM damage_cases WHERE id=? AND order_id=?');$q->execute([$case,(int)$p['id']]);if(!$q->fetch())Response::abort(404);$deadline=new DateTimeImmutable((string)$r->input('deadline'));$this->db->prepare("INSERT INTO damage_evidence_requests(damage_case_id,field_type,instructions,deadline,grace_ends_at,status,created_at) VALUES(?,?,?,?,?,'requested',NOW())")->execute([$case,(string)$r->input('field_type'),trim((string)$r->input('instructions')),$deadline->format('Y-m-d H:i:s'),$deadline->modify('+1 hour')->format('Y-m-d H:i:s')]);$this->db->prepare("UPDATE damage_cases SET status='evidence_requested' WHERE id=?")->execute([$case]);Session::flash('success','Nachforderung erstellt.');Response::redirect('/admin/auftraege/'.$p['id']);}
 public function decideDamage(Request $r,array $p):void{
  $orderId=(int)$p['id'];$caseId=(int)$p['caseId'];$decision=(string)$r->input('decision');$q=$this->db->prepare('SELECT * FROM damage_cases WHERE id=? AND order_id=?');$q->execute([$caseId,$orderId]);$case=$q->fetch();if(!$case)Response::abort(404);
  if($decision==='rejected'){
   $this->db->prepare("UPDATE damage_cases SET status='rejected',decision_note=?,decided_at=NOW() WHERE id=?")->execute([trim((string)$r->input('note')),$caseId]);
   Session::flash('success','Beschädigung abgelehnt; Auftrag läuft mit demselben Artikel weiter.');
  }elseif($decision==='recognized'){
   $this->db->beginTransaction();
   try{
    $orderQ=$this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$orderQ->execute([$orderId]);$order=$orderQ->fetch();if(!$order)Response::abort(404);
    $this->db->prepare("UPDATE damage_cases SET status='recognized',decision_note=?,decided_at=NOW() WHERE id=?")->execute([trim((string)$r->input('note')),$caseId]);
    $this->db->prepare("UPDATE order_runs SET status='restarted',ended_at=NOW() WHERE id=?")->execute([$case['order_run_id']]);
    $this->db->prepare("UPDATE order_days SET status='ended_by_restart' WHERE order_id=? AND order_run_id=? AND status IN('planned','active')")->execute([$orderId,$case['order_run_id']]);

    $activeOptions=$this->db->prepare('SELECT * FROM order_options WHERE order_id=? AND is_active=1 FOR UPDATE');$activeOptions->execute([$orderId]);$activeOptions=$activeOptions->fetchAll();$optionTotal=0.0;
    foreach($activeOptions as $option){
     $price=(float)$option['price'];$optionTotal+=$price;
     $this->db->prepare('UPDATE order_options SET is_active=0,removed_at=NOW() WHERE id=?')->execute([$option['id']]);
     $this->db->prepare("INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,status,metadata_json,created_at) VALUES(?,'option_remove',?,?,'active',?,NOW())")->execute([$orderId,'Option wegen Neustart zurückgesetzt: '.$option['name'],-$price,json_encode(['damage_case_id'=>$caseId,'order_option_id'=>$option['id']],JSON_UNESCAPED_UNICODE)]);
    }
    if($optionTotal!=0.0)$this->db->prepare('UPDATE orders SET current_total=GREATEST(0,current_total-?),updated_at=NOW() WHERE id=?')->execute([$optionTotal,$orderId]);

    $walletQ=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$walletQ->execute([$order['seller_id']]);$wallet=$walletQ->fetch();if(!$wallet)throw new \RuntimeException('Wallet nicht gefunden.');
    if($optionTotal>0){if((float)$wallet['balance_reserved']<$optionTotal)throw new \RuntimeException('Reservierter Walletbetrag ist inkonsistent.');$this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved-?,updated_at=NOW() WHERE id=?')->execute([$optionTotal,$wallet['id']]);}

    $currentQ=$this->db->prepare('SELECT current_total FROM orders WHERE id=?');$currentQ->execute([$orderId]);$restartAmount=(float)$currentQ->fetchColumn();
    $walletRefresh=$this->db->prepare('SELECT balance_reserved FROM wallets WHERE id=?');$walletRefresh->execute([$wallet['id']]);$reserved=(float)$walletRefresh->fetchColumn();if($reserved<$restartAmount)throw new \RuntimeException('Reservierter Walletbetrag reicht für die Neustart-Historisierung nicht aus.');
    $afterCancel=$reserved-$restartAmount;$this->db->prepare('UPDATE wallets SET balance_reserved=?,updated_at=NOW() WHERE id=?')->execute([$afterCancel,$wallet['id']]);
    $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,balance_after,metadata_json,created_at) VALUES(?,?,'restart_cancel','cancelled',?,?,?,NOW())")->execute([$wallet['id'],$orderId,-$restartAmount,$afterCancel,json_encode(['damage_case_id'=>$caseId,'old_run_id'=>$case['order_run_id']],JSON_UNESCAPED_UNICODE)]);
    $afterReserve=$afterCancel+$restartAmount;$this->db->prepare('UPDATE wallets SET balance_reserved=?,updated_at=NOW() WHERE id=?')->execute([$afterReserve,$wallet['id']]);
    $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,balance_after,metadata_json,created_at) VALUES(?,?,'restart_reservation','reserved',?,?,?,NOW())")->execute([$wallet['id'],$orderId,$restartAmount,$afterReserve,json_encode(['damage_case_id'=>$caseId],JSON_UNESCAPED_UNICODE)]);

    $n=$this->db->prepare('SELECT COALESCE(MAX(run_no),0)+1 FROM order_runs WHERE order_id=? FOR UPDATE');$n->execute([$orderId]);$runNo=(int)$n->fetchColumn();
    $this->db->prepare("INSERT INTO order_runs(order_id,run_no,status,restart_reason,created_at) VALUES(?,?,'preparation',?,NOW())")->execute([$orderId,$runNo,'Anerkannte Beschädigung #'.$caseId]);$newRunId=(int)$this->db->lastInsertId();
    (new OrderService($this->db))->initializePrechecksForRun($orderId,$newRunId);
    $this->db->prepare("UPDATE orders SET status='precheck',phase='preparation',started_at=NULL,updated_at=NOW() WHERE id=?")->execute([$orderId]);
    $this->db->prepare("UPDATE damage_cases SET status='restart_started' WHERE id=?")->execute([$caseId]);
    $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,?,'order_restarted','admin',?,?,NOW())")->execute([$order['seller_id'],$orderId,$this->auth->admin()['id']??null,json_encode(['damage_case_id'=>$caseId,'run_no'=>$runNo,'options_reset'=>count($activeOptions),'restart_amount'=>$restartAmount],JSON_UNESCAPED_UNICODE)]);
    $this->db->commit();
    $this->systemChat($orderId,'Beschädigung anerkannt. Durchlauf '.$runNo.' beginnt mit neuer Artikelwahl, vollständiger Vorabkontrolle und neu auszuwählenden Optionen. Bereits bestätigte Verstöße und Verlängerungen bleiben historisch erhalten.');
    Session::flash('success','Beschädigung anerkannt; neuer Durchlauf mit neuer Vorabkontrolle und neuer Optionsauswahl angelegt.');
   }catch(\Throwable $e){
    if($this->db->inTransaction())$this->db->rollBack();
    Session::flash('error',$e->getMessage());
   }
  }
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 private function resolveComponent(int $orderId,int $requested,bool $physicalOnly):int{
  if($requested>0){
   $sql='SELECT id FROM order_components WHERE id=? AND order_id=?'.($physicalOnly?" AND component_type='physical'":'');
   $q=$this->db->prepare($sql);$q->execute([$requested,$orderId]);$id=$q->fetchColumn();if($id)return (int)$id;
   throw new \RuntimeException('Ausgewählter Auftragsbestandteil ist ungültig.');
  }
  $sql='SELECT id FROM order_components WHERE order_id=?'.($physicalOnly?" AND component_type='physical'":'').' ORDER BY id';
  $q=$this->db->prepare($sql);$q->execute([$orderId]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
  if(count($ids)===1)return (int)$ids[0];
  throw new \RuntimeException('Bei einem Kombi-Auftrag muss der betroffene Bestandteil ausgewählt werden.');
 }
 private function systemChat(int $orderId,string $message):void{$q=$this->db->prepare('SELECT id FROM chats WHERE order_id=?');$q->execute([$orderId]);if($id=$q->fetchColumn())$this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,message,created_at) VALUES(?,'system',?,NOW())")->execute([$id,$message]);}
 private function ensureViolation(int $orderId,string $type,string $sourceType,int $sourceId,string $desc,?int $orderComponentId=null):void{$q=$this->db->prepare('SELECT id FROM violations WHERE order_id=? AND violation_type=? AND source_type=? AND source_id=?');$q->execute([$orderId,$type,$sourceType,$sourceId]);if($q->fetch())return;$this->db->prepare("INSERT INTO violations(order_id,order_component_id,violation_type,source_type,source_id,description,status,provisional_extension,created_at) VALUES(?,?,?,?,?,?,'open',1,NOW())")->execute([$orderId,$orderComponentId,$type,$sourceType,$sourceId,$desc]);$v=(int)$this->db->lastInsertId();$this->db->prepare("INSERT INTO extension_days(order_id,order_component_id,violation_id,source_type,source_id,reason,is_provisional,is_paid,created_at) VALUES(?,?,?,'violation',?,?,1,0,NOW())")->execute([$orderId,$orderComponentId,$v,$sourceId,$desc]);}
}