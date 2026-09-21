<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use App\Services\OrderService;use App\Services\NotificationService;use App\Services\Mailer;use App\Services\CategoryFieldService;use PDO;use DateTimeImmutable;
final class AdminOrderController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function index(Request $r):void{
  $term=trim((string)$r->input('q'));$status=trim((string)$r->input('status'));$phase=trim((string)$r->input('phase'));
  $where=[];$params=[];
  if($term!==''){$like='%'.$term.'%';$where[]="(o.order_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR ov.title LIKE ? OR EXISTS(SELECT 1 FROM order_offer_items ooi WHERE ooi.order_id=o.id AND ooi.title LIKE ?))";array_push($params,$like,$like,$like,$like,$like,$like);}
  if($status!==''){$where[]='o.status=?';$params[]=$status;}
  if($phase!==''){$where[]='o.phase=?';$params[]=$phase;}
  $sql="SELECT o.*,s.first_name,s.last_name,s.email,ov.title,(SELECT COUNT(*) FROM order_offer_items ooi WHERE ooi.order_id=o.id) offer_count,COUNT(oc.id) component_count,SUM(oc.component_type='physical') physical_count,SUM(oc.component_type='digital') digital_count FROM orders o JOIN sellers s ON s.id=o.seller_id JOIN offer_versions ov ON ov.id=o.offer_version_id LEFT JOIN order_components oc ON oc.order_id=o.id";
  if($where)$sql.=' WHERE '.implode(' AND ',$where);
  $sql.=" GROUP BY o.id ORDER BY o.updated_at DESC,o.id DESC LIMIT 250";
  $q=$this->db->prepare($sql);$q->execute($params);
  $statuses=$this->db->query("SELECT DISTINCT status FROM orders ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
  $phases=$this->db->query("SELECT DISTINCT phase FROM orders ORDER BY phase")->fetchAll(PDO::FETCH_COLUMN);
  $rows=$q->fetchAll();foreach($rows as &$row){if((int)($row['offer_count']??0)>1)$row['title']='Sammelauftrag · '.(int)$row['offer_count'].' Angebote';}unset($row);View::render($this->root,'admin/orders',['pageTitle'=>'Aufträge','orders'=>$rows,'filterTerm'=>$term,'filterStatus'=>$status,'filterPhase'=>$phase,'statuses'=>$statuses,'phases'=>$phases]);
 }
 public function show(Request $r,array $p):void{
  $q=$this->db->prepare("SELECT o.*,s.first_name,s.last_name,s.email,ov.title,c.is_digital FROM orders o JOIN sellers s ON s.id=o.seller_id JOIN offer_versions ov ON ov.id=o.offer_version_id JOIN offers off ON off.id=o.offer_id JOIN categories c ON c.id=off.category_id WHERE o.id=?");$q->execute([(int)$p['id']]);$o=$q->fetch();if(!$o)Response::abort(404);
  $components=$this->db->prepare("SELECT oc.*,c.name category_name FROM order_components oc JOIN categories c ON c.id=oc.category_id WHERE oc.order_id=? ORDER BY oc.sort_order,oc.id");$components->execute([$o['id']]);$components=$components->fetchAll();
  $run=$this->db->prepare('SELECT * FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$run=$run->fetch();
  $itemsQ=$this->db->prepare("SELECT oi.*,orr.run_no,oc.title component_title,c.name category_name FROM order_items oi JOIN order_runs orr ON orr.id=oi.order_run_id LEFT JOIN order_components oc ON oc.id=oi.order_component_id JOIN categories c ON c.id=oi.category_id WHERE oi.order_id=? ORDER BY orr.run_no DESC,oc.sort_order,oi.id");$itemsQ->execute([$o['id']]);$orderItems=$itemsQ->fetchAll();
  $categoryFields=(new CategoryFieldService($this->db))->groupedForCategories(array_map(fn($co)=>(int)$co['category_id'],$components));
  $pre=$this->db->prepare("SELECT pr.*,oc.title component_title,(SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='accepted') accepted_count,(SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='pending') pending_count,(SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='rejected') rejected_count FROM precheck_requirements pr LEFT JOIN order_components oc ON oc.id=pr.order_component_id WHERE pr.order_id=? AND pr.order_run_id=? ORDER BY oc.sort_order,pr.sort_order,pr.id");$pre->execute([$o['id'],$run['id']]);$precheckRequirements=$pre->fetchAll();
  $e=$this->db->prepare("SELECT e.*,pr.label precheck_label,oc.title component_title FROM evidences e LEFT JOIN precheck_requirements pr ON pr.id=e.precheck_requirement_id LEFT JOIN order_components oc ON oc.id=e.order_component_id WHERE e.order_id=? ORDER BY e.created_at DESC");$e->execute([$o['id']]);$v=$this->db->prepare("SELECT v.*,oc.title component_title FROM violations v LEFT JOIN order_components oc ON oc.id=v.order_component_id WHERE v.order_id=? ORDER BY v.created_at DESC");$v->execute([$o['id']]);$d=$this->db->prepare("SELECT od.*,oc.title component_title FROM order_days od LEFT JOIN order_components oc ON oc.id=od.order_component_id WHERE od.order_id=? ORDER BY od.calendar_date,oc.sort_order");$d->execute([$o['id']]);
  $sp=$this->db->prepare("SELECT sr.*,oc.title component_title,(SELECT COUNT(*) FROM evidences x WHERE x.spontaneous_request_id=sr.id) uploaded_count FROM spontaneous_requests sr LEFT JOIN order_components oc ON oc.id=sr.order_component_id WHERE sr.order_id=? ORDER BY sr.created_at DESC");$sp->execute([$o['id']]);
  $tasks=$this->db->prepare("SELECT te.*,t.title,t.description,t.config_json,oc.title component_title FROM task_executions te JOIN tasks t ON t.id=te.task_id LEFT JOIN order_components oc ON oc.id=t.order_component_id WHERE t.order_id=? ORDER BY te.due_at DESC");$tasks->execute([$o['id']]);
  $tpl=$this->db->query("SELECT id,title FROM task_templates WHERE is_active=1 ORDER BY title")->fetchAll();
  $damage=$this->db->prepare("SELECT dc.*,oc.title component_title FROM damage_cases dc LEFT JOIN order_components oc ON oc.id=dc.order_component_id WHERE dc.order_id=? ORDER BY dc.created_at DESC");$damage->execute([$o['id']]);
  $damageReq=$this->db->prepare("SELECT dr.* FROM damage_evidence_requests dr JOIN damage_cases dc ON dc.id=dr.damage_case_id WHERE dc.order_id=? ORDER BY dr.created_at DESC");$damageReq->execute([$o['id']]);
  $m=$this->db->prepare('SELECT cm.* FROM chat_messages cm JOIN chats c ON c.id=cm.chat_id WHERE c.order_id=? ORDER BY cm.created_at');$m->execute([$o['id']]);
  $shipping=$this->db->prepare("SELECT sw.*,ra.label,ra.recipient_name,ra.street,ra.postal_code,ra.city,ra.country_code,s.status shipment_status,s.tracking_number,s.shipped_at,s.received_at FROM shipping_workflows sw LEFT JOIN recipient_addresses ra ON ra.id=sw.recipient_address_id LEFT JOIN shipments s ON s.shipping_workflow_id=sw.id WHERE sw.order_id=?");$shipping->execute([$o['id']]);$shipping=$shipping->fetch();
  $shippingSteps=[];if($shipping){$ss=$this->db->prepare('SELECT * FROM shipping_steps WHERE shipping_workflow_id=? ORDER BY step_no');$ss->execute([$shipping['id']]);$shippingSteps=$ss->fetchAll();}
  $addresses=$this->db->query("SELECT id,label,recipient_name,street,postal_code,city FROM recipient_addresses WHERE is_active=1 ORDER BY label")->fetchAll();
  $final=$this->db->prepare('SELECT * FROM final_reviews WHERE order_id=?');$final->execute([$o['id']]);$final=$final->fetch();
  $orderOptionsQ=$this->db->prepare('SELECT * FROM order_options WHERE order_id=? AND is_active=1 ORDER BY id');$orderOptionsQ->execute([$o['id']]);$orderOptions=$orderOptionsQ->fetchAll();
  $availableOptionsQ=$this->db->prepare("SELECT oo.* FROM offer_options oo WHERE oo.offer_version_id=? AND oo.is_active=1 AND NOT EXISTS(SELECT 1 FROM order_options x WHERE x.order_id=? AND x.offer_option_id=oo.id AND x.is_active=1) ORDER BY oo.sort_order,oo.id");$availableOptionsQ->execute([$o['offer_version_id'],$o['id']]);$availableOptions=$availableOptionsQ->fetchAll();
  $adjustQ=$this->db->prepare('SELECT * FROM order_adjustments WHERE order_id=? ORDER BY created_at DESC,id DESC');$adjustQ->execute([$o['id']]);$adjustments=$adjustQ->fetchAll();
  $consentQ=$this->db->prepare('SELECT * FROM order_acceptance_consents WHERE order_id=? LIMIT 1');$consentQ->execute([$o['id']]);$acceptanceConsent=$consentQ->fetch()?:null;
  $digitalComponents=$this->db->prepare("SELECT dc.*,oc.title component_title FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id WHERE oc.order_id=? ORDER BY dc.id");$digitalComponents->execute([$o['id']]);$digitalComponents=$digitalComponents->fetchAll();
  $digitalVersions=[];$revisionRounds=[];$revisionItems=[];$digitalRightsEvents=[];
  foreach($digitalComponents as $dc){
   $v=$this->db->prepare("SELECT dv.*,ds.status submission_status,ds.finalized_at FROM digital_versions dv JOIN digital_submissions ds ON ds.id=dv.digital_submission_id WHERE ds.digital_component_id=? ORDER BY dv.version_no DESC");$v->execute([$dc['id']]);$digitalVersions[$dc['id']]=$v->fetchAll();
   $rr=$this->db->prepare("SELECT * FROM revision_rounds WHERE digital_component_id=? ORDER BY round_no DESC");$rr->execute([$dc['id']]);$revisionRounds[$dc['id']]=$rr->fetchAll();
   foreach($revisionRounds[$dc['id']] as $round){$ri=$this->db->prepare('SELECT * FROM revision_items WHERE revision_round_id=? ORDER BY id');$ri->execute([$round['id']]);$revisionItems[$round['id']]=$ri->fetchAll();}
   $re=$this->db->prepare("SELECT * FROM digital_rights_events WHERE digital_component_id=? ORDER BY created_at,id");$re->execute([$dc['id']]);$digitalRightsEvents[$dc['id']]=$re->fetchAll();
  }
  View::render($this->root,'admin/order',['pageTitle'=>'Auftrag #'.$o['order_number'],'order'=>$o,'components'=>$components,'run'=>$run,'orderItems'=>$orderItems,'categoryFields'=>$categoryFields,'precheckRequirements'=>$precheckRequirements,'evidences'=>$e->fetchAll(),'violations'=>$v->fetchAll(),'days'=>$d->fetchAll(),'spontaneous'=>$sp->fetchAll(),'taskExecutions'=>$tasks->fetchAll(),'taskTemplates'=>$tpl,'damageCases'=>$damage->fetchAll(),'damageRequests'=>$damageReq->fetchAll(),'messages'=>$m->fetchAll(),'shipping'=>$shipping,'shippingSteps'=>$shippingSteps,'addresses'=>$addresses,'finalReview'=>$final,'orderOptions'=>$orderOptions,'availableOptions'=>$availableOptions,'adjustments'=>$adjustments,'acceptanceConsent'=>$acceptanceConsent,'digitalComponents'=>$digitalComponents,'digitalVersions'=>$digitalVersions,'revisionRounds'=>$revisionRounds,'revisionItems'=>$revisionItems,'digitalRightsEvents'=>$digitalRightsEvents]);
 }
 public function reviewEvidence(Request $r,array $p):void{
  $status=(string)$r->input('decision');if(!in_array($status,['accepted','rejected'],true))Response::abort(422);
  $reason=$status==='rejected'?trim((string)$r->input('reason')):null;$note=$status==='rejected'?trim((string)$r->input('note')):null;$deadline=null;$grace=null;
  if($status==='rejected'&&trim((string)$r->input('retake_deadline'))!==''){$d=new DateTimeImmutable((string)$r->input('retake_deadline'));$deadline=$d->format('Y-m-d H:i:s');$grace=$d->modify('+1 hour')->format('Y-m-d H:i:s');}
  $q=$this->db->prepare('UPDATE evidences SET review_status=?,rejection_reason=?,rejection_note=?,retake_deadline=?,retake_grace_ends_at=? WHERE id=? AND order_id=?');
  $q->execute([$status,$reason?:null,$note?:null,$deadline,$grace,(int)$p['evidenceId'],(int)$p['id']]);
  if($status==='rejected'){$msg='Ein Nachweis wurde beanstandet.'.($deadline?' Bitte reiche die Nachaufnahme bis '.date('d.m.Y H:i',strtotime($deadline)).' ein.':' Bitte prüfe die Begründung im Auftrag.');$this->notifySeller((int)$p['id'],'evidence','Nachweis beanstandet',$msg,true);}
  Session::flash('success',$status==='accepted'?'Nachweis wurde akzeptiert.':'Nachweis wurde beanstandet'.($deadline?' und mit Nachforderungsfrist versehen.':'.'));Response::redirect('/admin/auftraege/'.$p['id']);
 }
 public function approvePrecheck(Request $r,array $p):void{try{(new OrderService($this->db))->startAfterPrecheck((int)$p['id']);$this->notifySeller((int)$p['id'],'order_start','Auftrag gestartet','Die Vorabkontrolle wurde vollständig freigegeben. Dein Auftrag ist jetzt gestartet.',true);Session::flash('success','Vorabkontrolle freigegeben. Auftrag wurde unmittelbar gestartet.');}catch(\Throwable $e){Session::flash('error',$e->getMessage());}Response::redirect('/admin/auftraege/'.$p['id']);}
 public function decideViolation(Request $r,array $p):void{
  $decision=(string)$r->input('decision');$id=(int)$p['violationId'];$q=$this->db->prepare('SELECT * FROM violations WHERE id=? AND order_id=?');$q->execute([$id,(int)$p['id']]);$v=$q->fetch();if(!$v)Response::abort(404);
  if($decision==='confirmed'&&$v['status']!=='confirmed'){$this->db->beginTransaction();try{$this->db->prepare("UPDATE violations SET status='confirmed',confirmed_at=NOW(),decided_at=NOW(),provisional_extension=0 WHERE id=?")->execute([$id]);$x=$this->db->prepare('SELECT id FROM extension_days WHERE violation_id=? ORDER BY id LIMIT 1 FOR UPDATE');$x->execute([$id]);$ext=$x->fetchColumn();if($ext){$this->db->prepare('UPDATE extension_days SET is_provisional=0,reason=? WHERE id=?')->execute([$v['description'],$ext]);$ext=(int)$ext;}else{$this->db->prepare("INSERT INTO extension_days(order_id,order_component_id,violation_id,source_type,source_id,reason,is_provisional,is_paid,created_at) VALUES(?,?,?,'violation',?,?,0,0,NOW())")->execute([$p['id'],$v['order_component_id']?:null,$id,$id,$v['description']]);$ext=(int)$this->db->lastInsertId();}$this->applyConfirmedExtension((int)$p['id'],$v['order_component_id']?(int)$v['order_component_id']:null,'violation_extension',$ext,$v['description']);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
  elseif($decision==='discarded'){$this->db->prepare("UPDATE violations SET status='discarded',provisional_extension=0,decided_at=NOW() WHERE id=?")->execute([$id]);$this->db->prepare("DELETE FROM extension_days WHERE violation_id=? AND is_provisional=1")->execute([$id]);}
  if(in_array($decision,['confirmed','discarded'],true)){$this->notifySeller((int)$p['id'],'violation',$decision==='confirmed'?'Verstoß bestätigt':'Prüffall verworfen',$decision==='confirmed'?'Ein Prüffall wurde als Verstoß bestätigt. Eine daraus folgende Verlängerung ist im Auftrag sichtbar.':'Ein Prüffall wurde verworfen und erzeugt keine bestätigte Verlängerung.',true);}
  Session::flash('success','Verstoßentscheidung gespeichert.');Response::redirect('/admin/auftraege/'.$p['id']);
 }
 public function addManualDay(Request $r,array $p):void{
  $orderId=(int)$p['id'];$componentId=(int)$r->input('order_component_id');$paid=(bool)$r->input('is_paid');$amount=$paid?round((float)str_replace(',','.',(string)$r->input('amount')),2):null;$reason=trim((string)$r->input('reason'))?:'Manueller Zusatztag';
  $this->db->beginTransaction();try{
   $component=$this->db->prepare("SELECT * FROM order_components WHERE id=? AND order_id=? AND component_type='physical' FOR UPDATE");$component->execute([$componentId,$orderId]);$component=$component->fetch();if(!$component)throw new \RuntimeException('Bitte einen gültigen physischen Bestandteil auswählen.');
   if($paid&&($amount===null||$amount<=0))throw new \RuntimeException('Für einen bezahlten Zusatztag ist ein positiver Betrag erforderlich.');
   $this->db->prepare('INSERT INTO manual_extra_days(order_id,order_component_id,reason,is_paid,amount,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$orderId,$componentId,$reason,(int)$paid,$amount]);$id=(int)$this->db->lastInsertId();
   $this->appendDay($orderId,$componentId,'manual_extension',$id,$reason,$paid,$amount);
   if($paid){
    $o=$this->db->prepare('SELECT seller_id FROM orders WHERE id=?');$o->execute([$orderId]);$sid=(int)$o->fetchColumn();$w=$this->db->prepare('SELECT id FROM wallets WHERE seller_id=? FOR UPDATE');$w->execute([$sid]);$wid=(int)$w->fetchColumn();
    $this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved+?,updated_at=NOW() WHERE id=?')->execute([$amount,$wid]);
    $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'paid_extra_day','reserved',?,?,NOW())")->execute([$wid,$orderId,$amount,json_encode(['order_component_id'=>$componentId,'manual_extra_day_id'=>$id],JSON_UNESCAPED_UNICODE)]);
    $this->db->prepare('UPDATE orders SET current_total=current_total+?,updated_at=NOW() WHERE id=?')->execute([$amount,$orderId]);
   }
   $this->db->commit();$this->notifySeller($orderId,'extra_day',$paid?'Bezahlter Zusatztag hinzugefügt':'Zusatztag hinzugefügt','Für „'.$component['title'].'“ wurde ein '.($paid?'bezahlter':'unbezahlter').' Zusatztag hinzugefügt.'.($paid?' Vergütung: '.number_format((float)$amount,2,',','.').' €.':''),true);Session::flash('success','Zusatztag wurde an „'.$component['title'].'“ angehängt.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 private function applyConfirmedExtension(int $orderId,?int $componentId,string $type,int $sourceId,string $reason):void{
  if($componentId===null){
   $q=$this->db->prepare("SELECT id FROM order_components WHERE order_id=? AND component_type='physical' ORDER BY id");$q->execute([$orderId]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
   if(count($ids)===1)$componentId=(int)$ids[0];
  }
  if($componentId!==null){
   $q=$this->db->prepare('SELECT component_type FROM order_components WHERE id=? AND order_id=?');$q->execute([$componentId,$orderId]);$componentType=$q->fetchColumn();
   if($componentType==='physical'){$this->appendDay($orderId,$componentId,$type,$sourceId,$reason,false,null);return;}
   if($componentType==='digital'){
    $this->db->prepare("UPDATE digital_components SET deadline=CASE WHEN deadline IS NULL THEN DATE_ADD(NOW(),INTERVAL 1 DAY) ELSE DATE_ADD(deadline,INTERVAL 1 DAY) END,updated_at=NOW() WHERE order_component_id=?")->execute([$componentId]);
    $this->db->prepare("UPDATE revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id SET rr.deadline=DATE_ADD(rr.deadline,INTERVAL 1 DAY),rr.grace_ends_at=DATE_ADD(rr.grace_ends_at,INTERVAL 1 DAY) WHERE dc.order_component_id=? AND rr.status IN('open','submitted')")->execute([$componentId]);
    return;
   }
  }
  throw new \RuntimeException('Der bestätigte Verstoß konnte keinem verlängerbaren Auftragsbestandteil zugeordnet werden.');
 }
 private function appendDay(int $orderId,int $componentId,string $type,int $sourceId,string $reason,bool $paid,?float $amount):void{
  $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$orderId]);$rid=(int)$run->fetchColumn();
  $q=$this->db->prepare('SELECT MAX(calendar_date),MAX(COALESCE(day_no,0)) FROM order_days WHERE order_id=? AND order_run_id=? AND order_component_id=?');$q->execute([$orderId,$rid,$componentId]);$max=$q->fetch(PDO::FETCH_NUM);$date=(new DateTimeImmutable($max[0]?:'today'))->modify('+1 day');$dayNo=(int)$max[1]+1;
  $this->db->prepare("INSERT INTO order_days(order_id,order_run_id,order_component_id,day_no,calendar_date,day_type,source_id,is_paid,paid_amount,reason,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,'planned',NOW())")->execute([$orderId,$rid,$componentId,$dayNo,$date->format('Y-m-d'),$type,$sourceId,(int)$paid,$amount,$reason]);$dayId=(int)$this->db->lastInsertId();
  $w=$this->db->prepare("SELECT ew.* FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE od.order_id=? AND od.order_run_id=? AND od.order_component_id=? AND od.day_type='regular' ORDER BY od.calendar_date DESC,ew.starts_at");$w->execute([$orderId,$rid,$componentId]);$templates=[];foreach($w->fetchAll() as $row){$key=$row['name'];if(!isset($templates[$key]))$templates[$key]=$row;}
  foreach($templates as $row){$startParts=date('H:i:s',strtotime($row['starts_at']));$endParts=date('H:i:s',strtotime($row['ends_at']));$starts=$date->format('Y-m-d').' '.$startParts;$ends=$date->format('Y-m-d').' '.$endParts;$grace=(new DateTimeImmutable($ends))->modify('+1 hour')->format('Y-m-d H:i:s');$this->db->prepare("INSERT INTO evidence_windows(order_day_id,name,starts_at,ends_at,grace_ends_at,required_count,camera_required,config_json,status,created_at) VALUES(?,?,?,?,?,?,?,?, 'open',NOW())")->execute([$dayId,$row['name'],$starts,$ends,$grace,$row['required_count'],(int)($row['camera_required']??0),$row['config_json']]);}
 }
 public function addAdjustment(Request $r,array $p):void{
  $orderId=(int)$p['id'];$type=(string)$r->input('adjustment_type');if(!in_array($type,['bonus','price_change','shipping_subsidy','other'],true))Response::abort(422);
  $amount=round((float)str_replace(',','.',(string)$r->input('amount')),2);$label=trim((string)$r->input('label'));$effective=$r->input('effective_day_no')!==''?(int)$r->input('effective_day_no'):null;
  if($label===''||$amount==0.0){Session::flash('error','Bezeichnung und ein Betrag ungleich 0 sind erforderlich.');Response::redirect('/admin/auftraege/'.$orderId);}
  if($type==='bonus'&&$amount<0){Session::flash('error','Ein Bonus muss positiv sein.');Response::redirect('/admin/auftraege/'.$orderId);}
  $this->db->beginTransaction();try{
   $oq=$this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$oq->execute([$orderId]);$o=$oq->fetch();if(!$o)Response::abort(404);if(in_array($o['status'],['completed','rejected','archived','paid'],true))throw new \RuntimeException('Der Auftragswert kann nach der finalen Entscheidung nicht mehr geändert werden.');$newTotal=(float)$o['current_total']+$amount;if($newTotal<0)throw new \RuntimeException('Der Auftragswert darf nicht negativ werden.');
   $wq=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$wq->execute([$o['seller_id']]);$w=$wq->fetch();if(!$w)throw new \RuntimeException('Wallet nicht gefunden.');$newReserved=(float)$w['balance_reserved']+$amount;if($newReserved<0)throw new \RuntimeException('Die Wallet-Reservierung kann durch diese Änderung nicht negativ werden.');
   $status=$type==='bonus'?'reserved':'active';$this->db->prepare('INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,effective_day_no,status,metadata_json,created_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$orderId,$type,$label,$amount,$effective,$status,json_encode(['admin_id'=>$this->auth->admin()['id']??null],JSON_UNESCAPED_UNICODE)]);$aid=(int)$this->db->lastInsertId();
   $this->db->prepare('UPDATE orders SET current_total=?,updated_at=NOW() WHERE id=?')->execute([$newTotal,$orderId]);$this->db->prepare('UPDATE wallets SET balance_reserved=?,updated_at=NOW() WHERE id=?')->execute([$newReserved,$w['id']]);
   $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'order_adjustment','reserved',?,?,NOW())")->execute([$w['id'],$orderId,$amount,json_encode(['adjustment_id'=>$aid,'type'=>$type,'label'=>$label,'effective_day_no'=>$effective],JSON_UNESCAPED_UNICODE)]);
   $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,?,'order_adjustment','admin',?,?,NOW())")->execute([$o['seller_id'],$orderId,$this->auth->admin()['id']??null,json_encode(['id'=>$aid,'type'=>$type,'label'=>$label,'amount'=>$amount,'effective_day_no'=>$effective],JSON_UNESCAPED_UNICODE)]);
   $this->db->commit();$this->notifySeller($orderId,'order_value','Auftragswert geändert',$label.': '.($amount>0?'+ ':'').number_format($amount,2,',','.').' €. Der neue Gesamtbetrag ist im Auftrag sichtbar.',true);Session::flash('success','Auftragswert wurde historisiert angepasst.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 private function notifySeller(int $orderId,string $type,string $title,string $message,bool $email=true):void{
  try{
   $q=$this->db->prepare('SELECT seller_id FROM orders WHERE id=?');$q->execute([$orderId]);$sellerId=(int)$q->fetchColumn();if(!$sellerId)return;
   $config=require $this->root.'/config/app.php';
   (new NotificationService($this->db,new Mailer($config)))->seller($sellerId,$type,$title,$message,'/konto/auftraege/'.$orderId,$email);
  }catch(\Throwable){}
 }
 public function cancelBonus(Request $r,array $p):void{
  $orderId=(int)$p['id'];$adjustmentId=(int)$p['adjustmentId'];$this->db->beginTransaction();try{
   $aq=$this->db->prepare("SELECT * FROM order_adjustments WHERE id=? AND order_id=? AND adjustment_type='bonus' AND status='reserved' FOR UPDATE");$aq->execute([$adjustmentId,$orderId]);$a=$aq->fetch();if(!$a)throw new \RuntimeException('Dieser Bonus kann nicht mehr entfernt werden.');
   $oq=$this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$oq->execute([$orderId]);$o=$oq->fetch();$wq=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$wq->execute([$o['seller_id']]);$w=$wq->fetch();
   $amount=(float)$a['amount'];$this->db->prepare("UPDATE order_adjustments SET status='cancelled',cancelled_at=NOW() WHERE id=?")->execute([$adjustmentId]);$this->db->prepare('UPDATE orders SET current_total=current_total-?,updated_at=NOW() WHERE id=?')->execute([$amount,$orderId]);$this->db->prepare('UPDATE wallets SET balance_reserved=GREATEST(0,balance_reserved-?),updated_at=NOW() WHERE id=?')->execute([$amount,$w['id']]);
   $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'bonus_reversal','cancelled',?,?,NOW())")->execute([$w['id'],$orderId,-$amount,json_encode(['adjustment_id'=>$adjustmentId],JSON_UNESCAPED_UNICODE)]);
   $this->db->commit();$this->notifySeller($orderId,'bonus','Bonus entfernt','Der vorgemerkte Bonus „'.$a['label'].'“ über '.number_format($amount,2,',','.').' € wurde entfernt.',true);Session::flash('success','Vorgemerkter Bonus wurde entfernt.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 public function addOption(Request $r,array $p):void{
  $orderId=(int)$p['id'];$optionId=(int)$r->input('offer_option_id');$this->db->beginTransaction();try{
   $oq=$this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$oq->execute([$orderId]);$o=$oq->fetch();if(!$o)Response::abort(404);if(in_array($o['status'],['completed','rejected','archived','paid'],true))throw new \RuntimeException('Optionen können nach der finalen Entscheidung nicht mehr geändert werden.');
   $op=$this->db->prepare("SELECT * FROM offer_options WHERE id=? AND offer_version_id=? AND is_active=1");$op->execute([$optionId,$o['offer_version_id']]);$option=$op->fetch();if(!$option)throw new \RuntimeException('Option ist nicht verfügbar.');
   $exists=$this->db->prepare('SELECT COUNT(*) FROM order_options WHERE order_id=? AND offer_option_id=? AND is_active=1');$exists->execute([$orderId,$optionId]);if((int)$exists->fetchColumn()>0)throw new \RuntimeException('Option ist bereits im Auftrag enthalten.');
   $price=(float)$option['price'];$this->db->prepare('INSERT INTO order_options(order_id,offer_option_id,name,price,config_snapshot,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$orderId,$optionId,$option['name'],$price,$option['requirements_json']]);$orderOptionId=(int)$this->db->lastInsertId();
   $this->db->prepare("INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,status,metadata_json,created_at) VALUES(?,'option_add',?,?,'active',?,NOW())")->execute([$orderId,'Option: '.$option['name'],$price,json_encode(['order_option_id'=>$orderOptionId,'offer_option_id'=>$optionId],JSON_UNESCAPED_UNICODE)]);
   if($price!=0.0){$wq=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$wq->execute([$o['seller_id']]);$w=$wq->fetch();$this->db->prepare('UPDATE orders SET current_total=current_total+?,updated_at=NOW() WHERE id=?')->execute([$price,$orderId]);$this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved+?,updated_at=NOW() WHERE id=?')->execute([$price,$w['id']]);$this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'option_add','reserved',?,?,NOW())")->execute([$w['id'],$orderId,$price,json_encode(['order_option_id'=>$orderOptionId],JSON_UNESCAPED_UNICODE)]);}
   $this->db->commit();$this->notifySeller($orderId,'option','Option hinzugefügt','Die Option „'.$option['name'].'“ wurde deinem Auftrag hinzugefügt.'.($price!=0.0?' Vergütung: +'.number_format($price,2,',','.').' €.':''),true);Session::flash('success','Option wurde dem laufenden Auftrag hinzugefügt.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 public function removeOption(Request $r,array $p):void{
  $orderId=(int)$p['id'];$orderOptionId=(int)$p['orderOptionId'];$this->db->beginTransaction();try{
   $oq=$this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$oq->execute([$orderId]);$o=$oq->fetch();if(!$o)Response::abort(404);if(in_array($o['status'],['completed','rejected','archived','paid'],true))throw new \RuntimeException('Optionen können nach der finalen Entscheidung nicht mehr geändert werden.');$qq=$this->db->prepare('SELECT * FROM order_options WHERE id=? AND order_id=? AND is_active=1 FOR UPDATE');$qq->execute([$orderOptionId,$orderId]);$option=$qq->fetch();if(!$option)throw new \RuntimeException('Option nicht gefunden.');$price=(float)$option['price'];
   $this->db->prepare('UPDATE order_options SET is_active=0,removed_at=NOW() WHERE id=?')->execute([$orderOptionId]);$this->db->prepare("INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,status,metadata_json,created_at) VALUES(?,'option_remove',?,?,'active',?,NOW())")->execute([$orderId,'Option entfernt: '.$option['name'],-$price,json_encode(['order_option_id'=>$orderOptionId,'offer_option_id'=>$option['offer_option_id']],JSON_UNESCAPED_UNICODE)]);
   if($price!=0.0){$wq=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$wq->execute([$o['seller_id']]);$w=$wq->fetch();if((float)$w['balance_reserved']<$price)throw new \RuntimeException('Reservierter Walletbetrag reicht für die Rücknahme nicht aus.');$this->db->prepare('UPDATE orders SET current_total=current_total-?,updated_at=NOW() WHERE id=?')->execute([$price,$orderId]);$this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved-?,updated_at=NOW() WHERE id=?')->execute([$price,$w['id']]);$this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'option_remove','cancelled',?,?,NOW())")->execute([$w['id'],$orderId,-$price,json_encode(['order_option_id'=>$orderOptionId],JSON_UNESCAPED_UNICODE)]);}
   $this->db->commit();$this->notifySeller($orderId,'option','Option entfernt','Die Option „'.$option['name'].'“ wurde aus deinem Auftrag entfernt.'.($price!=0.0?' Der Auftragswert wurde um '.number_format($price,2,',','.').' € reduziert.':''),true);Session::flash('success','Option wurde aus dem Auftrag entfernt und historisiert.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/admin/auftraege/'.$orderId);
 }
 public function sendChat(Request $r,array $p):void{$orderId=(int)$p['id'];$q=$this->db->prepare('SELECT id,is_readonly FROM chats WHERE order_id=?');$q->execute([$orderId]);$c=$q->fetch();if(!$c)Response::abort(404);$msg=trim((string)$r->input('message'));if($msg!==''&&!$c['is_readonly']){$this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,sender_id,message,created_at) VALUES(?,'admin',?,?,NOW())")->execute([$c['id'],$this->auth->admin()['id'],$msg]);$this->notifySeller($orderId,'chat','Neue Nachricht im Auftragschat','Der Betreiber hat dir im Auftragschat eine neue Nachricht geschrieben.',false);}Response::redirect('/admin/auftraege/'.$orderId);}
}