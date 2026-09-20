<?php
namespace App\Services;
use PDO;
final class SchedulerService{
 public function __construct(private PDO $db){}
 public function run():array{
  if(!$this->acquire('global-cron',300))return ['locked'=>true];
  $result=['evidence_violations'=>0,'task_violations'=>0,'expired_private_offers'=>0,'reminders'=>0,'cleanup'=>0];
  try{
   $result['evidence_violations']=$this->evidenceWindows();
   $result['task_violations']=$this->tasks();
   $result['expired_private_offers']=$this->privateOffers();
   $result['reminders']=$this->reminders();
   $result['cleanup']=$this->cleanup();
   return $result;
  }finally{$this->release('global-cron');}
 }
 private function acquire(string $key,int $seconds):bool{
  $this->db->beginTransaction();try{$q=$this->db->prepare('SELECT locked_until FROM scheduler_locks WHERE lock_key=? FOR UPDATE');$q->execute([$key]);$until=$q->fetchColumn();if($until&&strtotime($until)>time()){$this->db->rollBack();return false;}$this->db->prepare('INSERT INTO scheduler_locks(lock_key,locked_until,updated_at) VALUES(?,DATE_ADD(NOW(),INTERVAL ? SECOND),NOW()) ON DUPLICATE KEY UPDATE locked_until=VALUES(locked_until),updated_at=NOW()')->execute([$key,$seconds]);$this->db->commit();return true;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 private function release(string $key):void{$this->db->prepare('UPDATE scheduler_locks SET locked_until=NOW(),updated_at=NOW() WHERE lock_key=?')->execute([$key]);}
 private function evidenceWindows():int{
  $q=$this->db->query("SELECT ew.id,ew.required_count,od.order_id,o.seller_id FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.grace_ends_at<NOW() AND ew.status='open'");$made=0;
  foreach($q->fetchAll() as $w){$c=$this->db->prepare('SELECT COUNT(*) FROM evidences WHERE evidence_window_id=?');$c->execute([$w['id']]);$missing=max(0,(int)$w['required_count']-(int)$c->fetchColumn());for($slot=1;$slot<=$missing;$slot++){$sourceId=(int)$w['id']*1000+$slot;$v=$this->ensureViolation((int)$w['order_id'],'missing_evidence','evidence_window',$sourceId,'Pflichtnachweis im vorgesehenen Zeitfenster einschließlich Nachfrist fehlt.');if($v)$made++;}$this->db->prepare("UPDATE evidence_windows SET status='closed' WHERE id=?")->execute([$w['id']]);}
  return $made;
 }
 private function tasks():int{
  $q=$this->db->query("SELECT te.id,t.order_id FROM task_executions te JOIN tasks t ON t.id=te.task_id WHERE te.grace_ends_at<NOW() AND te.submitted_at IS NULL AND te.review_status='open'");$made=0;
  foreach($q->fetchAll() as $x){if($this->ensureViolation((int)$x['order_id'],'task_not_completed','task_execution',(int)$x['id'],'Zusatzaufgabe wurde innerhalb der Frist und Nachfrist nicht vollständig eingereicht.'))$made++;$this->db->prepare("UPDATE task_executions SET review_status='overdue' WHERE id=?")->execute([$x['id']]);}
  return $made;
 }
 private function privateOffers():int{
  $q=$this->db->query("SELECT id FROM offers WHERE is_private=1 AND status='active' AND private_offer_status='pending' AND acceptance_deadline<NOW()");$ids=$q->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id)$this->db->prepare("UPDATE offers SET private_offer_status='expired',updated_at=NOW() WHERE id=?")->execute([$id]);return count($ids);
 }
 private function reminders():int{
  $count=0;
  $windows=$this->db->query("SELECT ew.id,ew.name,ew.starts_at,ew.ends_at,od.order_id,o.seller_id FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.status='open' AND ew.starts_at BETWEEN DATE_SUB(NOW(),INTERVAL 2 MINUTE) AND DATE_ADD(NOW(),INTERVAL 62 MINUTE)")->fetchAll();
  foreach($windows as $w){$mins=(int)round((strtotime($w['starts_at'])-time())/60);$bucket=$mins>30?'60m':'start';$key='evidence:'.$w['id'].':'.$bucket;if($this->notify((int)$w['seller_id'],$key,'Nachweisfenster '.$w['name'],$mins>30?'Das Nachweisfenster beginnt in ungefähr einer Stunde.':'Das Nachweisfenster ist jetzt fällig.','/konto/auftraege/'.$w['order_id']))$count++;}
  $priv=$this->db->query("SELECT id,seller_id,title,acceptance_deadline FROM offers WHERE is_private=1 AND status='active' AND private_offer_status='pending' AND acceptance_deadline>NOW() AND acceptance_deadline<=DATE_ADD(NOW(),INTERVAL 25 HOUR)")->fetchAll();
  foreach($priv as $o){$left=strtotime($o['acceptance_deadline'])-time();$bucket=$left<=3600?'1h':'24h';$key='private-offer:'.$o['id'].':'.$bucket;if($this->notify((int)$o['seller_id'],$key,'Privatangebot läuft bald ab','Für „'.$o['title'].'“ endet die Annahmefrist '.($bucket==='1h'?'in ungefähr einer Stunde.':'innerhalb der nächsten 24 Stunden.'),'/angebote/'.$o['id']))$count++;}
  return $count;
 }
 private function ensureViolation(int $orderId,string $type,string $sourceType,int $sourceId,string $description):bool{
  $q=$this->db->prepare('SELECT id FROM violations WHERE order_id=? AND violation_type=? AND source_type=? AND source_id=?');$q->execute([$orderId,$type,$sourceType,$sourceId]);if($q->fetchColumn())return false;
  $this->db->beginTransaction();try{$i=$this->db->prepare("INSERT INTO violations(order_id,violation_type,source_type,source_id,description,status,provisional_extension,created_at) VALUES(?,?,?,?,?,'open',1,NOW())");$i->execute([$orderId,$type,$sourceType,$sourceId,$description]);$vid=(int)$this->db->lastInsertId();$this->db->prepare("INSERT INTO extension_days(order_id,violation_id,source_type,source_id,reason,is_provisional,is_paid,created_at) VALUES(?,?,'violation',?,?,1,0,NOW())")->execute([$orderId,$vid,$sourceId,$description]);$this->db->commit();return true;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 private function notify(int $sellerId,string $key,string $title,string $message,string $url):bool{
  try{$q=$this->db->prepare('INSERT INTO notifications(seller_id,dedupe_key,type,title,message,url,created_at) VALUES(?,?,\'reminder\',?,?,?,NOW())');$q->execute([$sellerId,$key,$title,$message,$url]);return true;}catch(\PDOException $e){if((int)$e->errorInfo[1]===1062)return false;throw $e;}
 }
 private function cleanup():int{$a=$this->db->exec("DELETE FROM rate_limits WHERE created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)");$b=$this->db->exec("DELETE FROM password_resets WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)");$c=$this->db->exec("DELETE FROM email_verifications WHERE expires_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");return (int)$a+(int)$b+(int)$c;}
}