<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use App\Services\OrderService;use App\Services\PrivateStorage;use App\Services\CategoryFieldService;use PDO;use RuntimeException;
final class OrderController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function acceptOffer(Request $r,array $p):void{
  $s=$this->auth->seller();
  try{
   foreach(['adult_confirmation'=>'Bitte bestätige deine Volljährigkeit.','own_goods_confirmation'=>'Bitte bestätige, dass Artikel und Inhalte von dir selbst stammen.','no_third_parties_confirmation'=>'Bitte bestätige, dass keine nicht einwilligenden Dritten beteiligt sind.','summary_confirmation'=>'Bitte die Auftragszusammenfassung vor der Annahme bestätigen.'] as $field=>$message){
    if(!$r->input($field))throw new RuntimeException($message);
   }
   $options=$r->input('options',[]);if(!is_array($options))$options=[];
   $consents=[
    'terms_version'=>'2026-09-20',
    'adult_confirmed'=>true,
    'own_goods_confirmed'=>true,
    'no_third_parties_confirmed'=>true,
    'summary_confirmed'=>true,
    'ip_address'=>$r->server['REMOTE_ADDR']??null,
    'user_agent'=>$r->server['HTTP_USER_AGENT']??null,
   ];
   $id=(new OrderService($this->db,$this->root))->accept((int)$s['id'],(int)$p['id'],$options,(bool)$r->input('rights_acceptance'),$consents);
   Session::flash('success','Auftrag wurde angenommen und für die Vorbereitung angelegt.');
   Response::redirect('/konto/auftraege/'.$id);
  }catch(\Throwable $e){Session::flash('error',$e->getMessage());Response::redirect('/angebote/'.$p['id']);}
 }
 public function show(Request $r,array $p):void{
  $s=$this->auth->seller();$q=$this->db->prepare("SELECT o.*,ov.title,ov.description,ov.duration_value,ov.duration_unit,ov.start_control_json,ov.evidence_json,c.name category_name,c.is_digital FROM orders o JOIN offer_versions ov ON ov.id=o.offer_version_id JOIN offers off ON off.id=o.offer_id JOIN categories c ON c.id=off.category_id WHERE o.id=? AND o.seller_id=?");$q->execute([(int)$p['id'],$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);
  $run=$this->db->prepare('SELECT * FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$run=$run->fetch();
  $sourceQ=$this->db->prepare("SELECT * FROM order_offer_items WHERE order_id=? ORDER BY sort_order,id");$sourceQ->execute([$o['id']]);$orderOffers=$sourceQ->fetchAll();if(count($orderOffers)>1){$o['title']='Sammelauftrag · '.count($orderOffers).' Angebote';$o['description']='Mehrere Angebote wurden gemeinsam als ein Auftrag abgeschlossen.';}
  $components=$this->db->prepare("SELECT oc.*,c.name category_name,ooi.title source_offer_title FROM order_components oc JOIN categories c ON c.id=oc.category_id LEFT JOIN order_offer_items ooi ON ooi.id=oc.order_offer_item_id WHERE oc.order_id=? ORDER BY oc.sort_order,oc.id");$components->execute([$o['id']]);$components=$components->fetchAll();
  $categoryFields=(new CategoryFieldService($this->db))->groupedForCategories(array_map(fn($co)=>(int)$co['category_id'],$components));
  $itemsQ=$this->db->prepare('SELECT * FROM order_items WHERE order_id=? AND order_run_id=? ORDER BY id');$itemsQ->execute([$o['id'],$run['id']]);$items=[];foreach($itemsQ->fetchAll() as $it)$items[(int)($it['order_component_id']??0)]=$it;$item=$items?reset($items):false;
  $preQ=$this->db->prepare("SELECT pr.*,oc.title component_title,(SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='accepted') accepted_count,(SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='pending') pending_count FROM precheck_requirements pr LEFT JOIN order_components oc ON oc.id=pr.order_component_id WHERE pr.order_id=? AND pr.order_run_id=? ORDER BY oc.sort_order,pr.sort_order,pr.id");$preQ->execute([$o['id'],$run['id']]);$precheckRequirements=$preQ->fetchAll();
  if(!$o['started_at'] && $run && !$precheckRequirements && array_filter($components,fn($co)=>$co['component_type']==='physical')){
   try{
    (new OrderService($this->db,$this->root))->initializePrechecksForRun((int)$o['id'],(int)$run['id']);
    $preQ->execute([$o['id'],$run['id']]);
    $precheckRequirements=$preQ->fetchAll();
   }catch(\Throwable $repairError){
    \App\Core\Logger::error($this->root,'Vorabkontrollen konnten nicht automatisch rekonstruiert werden.',['order_id'=>(int)$o['id'],'error'=>$repairError->getMessage()]);
   }
  }
  $ev=$this->db->prepare("SELECT e.*,ew.name window_name,ew.camera_required window_camera_required,pr.label precheck_label,pr.camera_required precheck_camera_required,oc.title component_title FROM evidences e LEFT JOIN evidence_windows ew ON ew.id=e.evidence_window_id LEFT JOIN precheck_requirements pr ON pr.id=e.precheck_requirement_id LEFT JOIN order_components oc ON oc.id=e.order_component_id WHERE e.order_id=? ORDER BY e.created_at DESC");$ev->execute([$o['id']]);
  $days=$this->db->prepare("SELECT od.*,oc.title component_title,(SELECT COUNT(*) FROM evidence_windows ew WHERE ew.order_day_id=od.id) window_count FROM order_days od LEFT JOIN order_components oc ON oc.id=od.order_component_id WHERE od.order_id=? ORDER BY calendar_date,oc.sort_order,COALESCE(day_no,0)");$days->execute([$o['id']]);
  $windows=$this->db->prepare("SELECT ew.*,od.day_no,od.calendar_date,od.order_component_id,oc.title component_title,(SELECT COUNT(*) FROM evidences e WHERE e.evidence_window_id=ew.id) uploaded_count FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id LEFT JOIN order_components oc ON oc.id=od.order_component_id WHERE od.order_id=? ORDER BY ew.starts_at,oc.sort_order");$windows->execute([$o['id']]);
  $viol=$this->db->prepare('SELECT * FROM violations WHERE order_id=? ORDER BY created_at DESC');$viol->execute([$o['id']]);
  $sp=$this->db->prepare("SELECT sr.*,oc.title component_title,(SELECT COUNT(*) FROM evidences e WHERE e.spontaneous_request_id=sr.id) uploaded_count FROM spontaneous_requests sr LEFT JOIN order_components oc ON oc.id=sr.order_component_id WHERE sr.order_id=? ORDER BY sr.created_at DESC");$sp->execute([$o['id']]);
  $tasks=$this->db->prepare("SELECT te.*,t.title,t.description,t.config_json,oc.title component_title FROM task_executions te JOIN tasks t ON t.id=te.task_id LEFT JOIN order_components oc ON oc.id=t.order_component_id WHERE t.order_id=? ORDER BY te.due_at");$tasks->execute([$o['id']]);
  $damage=$this->db->prepare("SELECT dc.*,oc.title component_title,(SELECT COUNT(*) FROM damage_evidence_requests dr WHERE dr.damage_case_id=dc.id AND dr.status='requested') open_requests FROM damage_cases dc LEFT JOIN order_components oc ON oc.id=dc.order_component_id WHERE dc.order_id=? ORDER BY dc.created_at DESC");$damage->execute([$o['id']]);
  $damageReq=$this->db->prepare("SELECT dr.*,dc.order_id FROM damage_evidence_requests dr JOIN damage_cases dc ON dc.id=dr.damage_case_id WHERE dc.order_id=? ORDER BY dr.created_at DESC");$damageReq->execute([$o['id']]);
  $chatInfoQ=$this->db->prepare('SELECT id,is_readonly FROM chats WHERE order_id=?');$chatInfoQ->execute([$o['id']]);$chatInfo=$chatInfoQ->fetch();$chatReadonly=$chatInfo?(bool)$chatInfo['is_readonly']:true;
  $chat=$this->db->prepare('SELECT * FROM chat_messages WHERE chat_id=? ORDER BY created_at');$chat->execute([$chatInfo['id']??0]);
  $shipping=$this->db->prepare("SELECT sw.*,ra.label,ra.recipient_name,ra.street,ra.postal_code,ra.city,ra.country_code,s.status shipment_status,s.tracking_number,s.shipped_at,s.received_at FROM shipping_workflows sw LEFT JOIN recipient_addresses ra ON ra.id=sw.recipient_address_id LEFT JOIN shipments s ON s.shipping_workflow_id=sw.id WHERE sw.order_id=?");$shipping->execute([$o['id']]);$shipping=$shipping->fetch();
  $shippingSteps=[];if($shipping){$ss=$this->db->prepare('SELECT * FROM shipping_steps WHERE shipping_workflow_id=? ORDER BY step_no');$ss->execute([$shipping['id']]);$shippingSteps=$ss->fetchAll();}
  $final=$this->db->prepare('SELECT decision,approved_amount,seller_message,decided_at FROM final_reviews WHERE order_id=?');$final->execute([$o['id']]);$final=$final->fetch();
  $orderOptionsQ=$this->db->prepare('SELECT * FROM order_options WHERE order_id=? AND is_active=1 ORDER BY id');$orderOptionsQ->execute([$o['id']]);$orderOptions=$orderOptionsQ->fetchAll();
  $offerOptions=[];if(count($orderOffers)<=1){$offerOptionsQ=$this->db->prepare("SELECT * FROM offer_options WHERE offer_version_id=? AND is_active=1 ORDER BY sort_order,id");$offerOptionsQ->execute([$o['offer_version_id']]);$offerOptions=$offerOptionsQ->fetchAll();}
  $adjustQ=$this->db->prepare("SELECT * FROM order_adjustments WHERE order_id=? AND status<>'cancelled' ORDER BY created_at,id");$adjustQ->execute([$o['id']]);$adjustments=$adjustQ->fetchAll();
  $consentQ=$this->db->prepare('SELECT * FROM order_acceptance_consents WHERE order_id=? AND seller_id=? LIMIT 1');$consentQ->execute([$o['id'],$s['id']]);$acceptanceConsent=$consentQ->fetch()?:null;
  $manualQ=$this->db->prepare('SELECT med.*,oc.title component_title FROM manual_extra_days med LEFT JOIN order_components oc ON oc.id=med.order_component_id WHERE med.order_id=? ORDER BY med.created_at,med.id');$manualQ->execute([$o['id']]);$manualExtraDays=$manualQ->fetchAll();
  $digitalComponents=$this->db->prepare("SELECT dc.*,oc.title component_title FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id WHERE oc.order_id=? ORDER BY dc.id");$digitalComponents->execute([$o['id']]);$digitalComponents=$digitalComponents->fetchAll();
  $digitalVersions=[];$revisionRounds=[];$revisionItems=[];
  foreach($digitalComponents as $dc){
   $v=$this->db->prepare("SELECT dv.*,ds.status submission_status,ds.finalized_at FROM digital_versions dv JOIN digital_submissions ds ON ds.id=dv.digital_submission_id WHERE ds.digital_component_id=? ORDER BY dv.version_no DESC");$v->execute([$dc['id']]);$digitalVersions[$dc['id']]=$v->fetchAll();
   $rr=$this->db->prepare("SELECT * FROM revision_rounds WHERE digital_component_id=? ORDER BY round_no DESC");$rr->execute([$dc['id']]);$revisionRounds[$dc['id']]=$rr->fetchAll();
   foreach($revisionRounds[$dc['id']] as $round){$ri=$this->db->prepare('SELECT * FROM revision_items WHERE revision_round_id=? ORDER BY id');$ri->execute([$round['id']]);$revisionItems[$round['id']]=$ri->fetchAll();}
  }
  View::render($this->root,'seller/order',['pageTitle'=>'Auftrag #'.$o['order_number'],'order'=>$o,'run'=>$run,'item'=>$item,'items'=>$items,'components'=>$components,'categoryFields'=>$categoryFields,'precheckRequirements'=>$precheckRequirements,'evidences'=>$ev->fetchAll(),'days'=>$days->fetchAll(),'windows'=>$windows->fetchAll(),'violations'=>$viol->fetchAll(),'spontaneous'=>$sp->fetchAll(),'tasks'=>$tasks->fetchAll(),'damageCases'=>$damage->fetchAll(),'damageRequests'=>$damageReq->fetchAll(),'messages'=>$chat->fetchAll(),'shipping'=>$shipping,'shippingSteps'=>$shippingSteps,'finalReview'=>$final,'orderOptions'=>$orderOptions,'offerOptions'=>$offerOptions,'adjustments'=>$adjustments,'manualExtraDays'=>$manualExtraDays,'acceptanceConsent'=>$acceptanceConsent,'digitalComponents'=>$digitalComponents,'digitalVersions'=>$digitalVersions,'revisionRounds'=>$revisionRounds,'revisionItems'=>$revisionItems,'chatReadonly'=>$chatReadonly,'orderOffers'=>$orderOffers]);
 }
 public function saveItem(Request $r,array $p):void{
  $s=$this->auth->seller();$q=$this->db->prepare('SELECT * FROM orders WHERE id=? AND seller_id=?');$q->execute([(int)$p['id'],$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);if($o['started_at']){Session::flash('error','Nach Start der physischen Durchführung ist kein normaler Artikelwechsel mehr möglich.');Response::redirect('/konto/auftraege/'.$o['id']);}
  $componentId=(int)$r->input('order_component_id');$cq=$this->db->prepare("SELECT * FROM order_components WHERE id=? AND order_id=? AND component_type='physical'");$cq->execute([$componentId,$o['id']]);$component=$cq->fetch();if(!$component){Session::flash('error','Physischer Auftragsbestandteil wurde nicht gefunden.');Response::redirect('/konto/auftraege/'.$o['id']);}
  $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$rid=(int)$run->fetchColumn();
  try{
   $shortName=trim((string)$r->input('short_name'));if($shortName==='')throw new RuntimeException('Bitte eine kurze Artikelbezeichnung angeben.');
   $attributes=(new CategoryFieldService($this->db))->normalize((int)$component['category_id'],$r->input('attributes',[]));
   $this->db->beginTransaction();
   $this->db->prepare('DELETE FROM order_items WHERE order_id=? AND order_run_id=? AND order_component_id=?')->execute([$o['id'],$rid,$componentId]);
   $this->db->prepare('INSERT INTO order_items(order_id,order_run_id,order_component_id,category_id,short_name,size,color,brand,material,attributes_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$o['id'],$rid,$componentId,$component['category_id'],$shortName,trim((string)$r->input('size'))?:null,trim((string)$r->input('color'))?:null,trim((string)$r->input('brand'))?:null,trim((string)$r->input('material'))?:null,json_encode($attributes,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
   $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,?,'order_item_saved','seller',?,?,NOW())")->execute([$s['id'],$o['id'],$s['id'],json_encode(['order_component_id'=>$componentId,'category_id'=>(int)$component['category_id'],'short_name'=>$shortName,'attribute_keys'=>array_keys($attributes)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
   $this->db->commit();
   Session::flash('success','Artikelangaben für „'.$component['title'].'“ gespeichert.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function uploadEvidence(Request $r,array $p):void{
  $seller=$this->auth->seller();
  $q=$this->db->prepare('SELECT * FROM orders WHERE id=? AND seller_id=?');
  $q->execute([(int)$p['id'],$seller['id']]);
  $order=$q->fetch();
  if(!$order)Response::abort(404);

  try{
   $runQ=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');
   $runQ->execute([$order['id']]);
   $runId=(int)$runQ->fetchColumn();

   $type=(string)$r->input('evidence_type','regular');
   $windowId=$r->input('evidence_window_id')?(int)$r->input('evidence_window_id'):null;
   $componentId=null;
   $precheckRequirementId=null;
   $retakeOf=null;
   $cameraRequired=false;

   if($type==='retake'){
    $retakeOf=(int)$r->input('retake_of_evidence_id');
    $rq=$this->db->prepare("SELECT e.*,pr.camera_required precheck_camera,ew.camera_required window_camera
      FROM evidences e
      LEFT JOIN precheck_requirements pr ON pr.id=e.precheck_requirement_id
      LEFT JOIN evidence_windows ew ON ew.id=e.evidence_window_id
      WHERE e.id=? AND e.order_id=? AND e.order_run_id=? AND e.review_status='rejected'
        AND e.resolved_by_evidence_id IS NULL AND e.retake_deadline IS NOT NULL");
    $rq->execute([$retakeOf,$order['id'],$runId]);
    $original=$rq->fetch();
    if(!$original)throw new RuntimeException('Diese Nachaufnahme ist nicht mehr verfügbar.');
    if(!$original['retake_grace_ends_at']||time()>strtotime($original['retake_grace_ends_at']))throw new RuntimeException('Die Nachfrist für diese Nachaufnahme ist abgelaufen.');

    $componentId=$original['order_component_id']?(int)$original['order_component_id']:null;
    $precheckRequirementId=$original['precheck_requirement_id']?(int)$original['precheck_requirement_id']:null;
    $windowId=$original['evidence_window_id']?(int)$original['evidence_window_id']:null;
    $cameraRequired=((int)($original['precheck_camera']??0)===1)||((int)($original['window_camera']??0)===1);
   }elseif($type==='precheck'){
    if($order['started_at'])throw new RuntimeException('Vorabnachweise können nach Start nicht mehr ergänzt werden.');
    $precheckRequirementId=(int)$r->input('precheck_requirement_id');
    $pr=$this->db->prepare("SELECT pr.*,oc.id component_id FROM precheck_requirements pr JOIN order_components oc ON oc.id=pr.order_component_id WHERE pr.id=? AND pr.order_id=? AND pr.order_run_id=?");
    $pr->execute([$precheckRequirementId,$order['id'],$runId]);
    $req=$pr->fetch();
    if(!$req)throw new RuntimeException('Vorabanforderung ist ungültig.');
    $componentId=(int)$req['component_id'];
    $cameraRequired=(int)($req['camera_required']??0)===1;

    $it=$this->db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=? AND order_run_id=? AND order_component_id=?');
    $it->execute([$order['id'],$runId,$componentId]);
    if((int)$it->fetchColumn()<1)throw new RuntimeException('Bitte zuerst den konkreten Artikel dieses Bestandteils erfassen.');

    $old=$this->db->prepare("SELECT id FROM evidences WHERE order_id=? AND order_run_id=? AND precheck_requirement_id=? AND review_status='rejected' AND resolved_by_evidence_id IS NULL ORDER BY id DESC LIMIT 1");
    $old->execute([$order['id'],$runId,$precheckRequirementId]);
    $retakeOf=$old->fetchColumn()?:null;
   }else{
    if(!$windowId)throw new RuntimeException('Nachweisfenster fehlt.');
    $w=$this->db->prepare("SELECT ew.*,od.order_component_id FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE ew.id=? AND od.order_id=? AND od.order_run_id=? AND od.status<>'ended_by_restart'");
    $w->execute([$windowId,$order['id'],$runId]);
    $win=$w->fetch();
    if(!$win)throw new RuntimeException('Nachweisfenster ist ungültig oder gehört zu einem früheren Durchlauf.');
    $componentId=$win['order_component_id']?(int)$win['order_component_id']:null;
    if(time()<strtotime($win['starts_at']))throw new RuntimeException('Das Nachweisfenster hat noch nicht begonnen.');
    if(time()>strtotime($win['grace_ends_at']))throw new RuntimeException('Die Nachfrist ist abgelaufen.');
    $cameraRequired=(int)($win['camera_required']??0)===1;
   }

   $captureSource=in_array((string)$r->input('capture_source'),['live_camera','file_picker'],true)?(string)$r->input('capture_source'):'file_picker';
   if($cameraRequired&&$captureSource!=='live_camera')throw new RuntimeException('Für diesen Nachweis ist eine Aufnahme direkt über die Plattformkamera erforderlich.');

   $cfg=require $this->root.'/config/app.php';
   $file=(new PrivateStorage($cfg['private_storage']))->storeUploaded($r->files['evidence']??[],'evidence',['image/jpeg','image/png','image/webp'],12*1024*1024,480);
   $metadata=json_encode([
    'user_agent'=>$r->server['HTTP_USER_AGENT']??null,
    'capture_source'=>$captureSource,
    'retake'=>$retakeOf!==null,
    'image_width'=>$file['image_width']??null,
    'image_height'=>$file['image_height']??null,
    'image_megapixels'=>$file['image_megapixels']??null,
   ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

   $this->db->beginTransaction();
   $this->db->prepare("INSERT INTO evidences(order_id,order_run_id,order_component_id,precheck_requirement_id,retake_of_evidence_id,evidence_window_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,'pending',NOW())")
    ->execute([$order['id'],$runId,$componentId,$precheckRequirementId,$retakeOf,$windowId,$type,$file['path'],$file['original_name'],$file['mime'],$file['size'],$file['sha256'],$metadata]);
   $newEvidenceId=(int)$this->db->lastInsertId();

   if($retakeOf){
    $update=$this->db->prepare('UPDATE evidences SET resolved_by_evidence_id=? WHERE id=? AND resolved_by_evidence_id IS NULL');
    $update->execute([$newEvidenceId,$retakeOf]);
    if($update->rowCount()!==1)throw new RuntimeException('Diese Nachaufnahme wurde zwischenzeitlich bereits erledigt.');
   }

   $this->db->commit();
   Session::flash('success',$retakeOf?'Nachaufnahme wurde unverändert gespeichert und erneut zur Prüfung eingereicht.':'Nachweis wurde unverändert gespeichert und zur Prüfung eingereicht.');
  }catch(\Throwable $e){
   if($this->db->inTransaction())$this->db->rollBack();
   Session::flash('error',$e->getMessage());
  }

  Response::redirect('/konto/auftraege/'.$order['id']);
 }
 public function updateOptions(Request $r,array $p):void{
  $seller=$this->auth->seller();$orderId=(int)$p['id'];$selected=$r->input('options',[]);if(!is_array($selected))$selected=[];$selected=array_values(array_unique(array_map('intval',$selected)));
  $this->db->beginTransaction();try{
   $oq=$this->db->prepare('SELECT * FROM orders WHERE id=? AND seller_id=? FOR UPDATE');$oq->execute([$orderId,$seller['id']]);$order=$oq->fetch();if(!$order)Response::abort(404);
   if($order['started_at']||$order['phase']!=='preparation')throw new RuntimeException('Optionen können durch die Verkäuferin nur vor dem Auftragsstart geändert werden.');
   $sourceCountQ=$this->db->prepare('SELECT COUNT(*) FROM order_offer_items WHERE order_id=?');$sourceCountQ->execute([$orderId]);if((int)$sourceCountQ->fetchColumn()>1)throw new RuntimeException('Bei einem Sammelauftrag werden die Optionen beim gemeinsamen Checkout festgelegt und können danach nicht pauschal geändert werden.');
   $availableQ=$this->db->prepare("SELECT * FROM offer_options WHERE offer_version_id=? AND is_active=1 ORDER BY id");$availableQ->execute([$order['offer_version_id']]);$available=$availableQ->fetchAll();$catalog=[];foreach($available as $op)$catalog[(int)$op['id']]=$op;
   foreach($selected as $id)if(!isset($catalog[$id]))throw new RuntimeException('Mindestens eine ausgewählte Option ist nicht verfügbar.');
   $currentQ=$this->db->prepare('SELECT * FROM order_options WHERE order_id=? AND is_active=1 FOR UPDATE');$currentQ->execute([$orderId]);$current=$currentQ->fetchAll();$currentByOffer=[];foreach($current as $op)$currentByOffer[(int)$op['offer_option_id']]=$op;
   $oldTotal=array_sum(array_map(fn($x)=>(float)$x['price'],$current));$newTotal=0.0;foreach($selected as $id)$newTotal+=(float)$catalog[$id]['price'];$delta=round($newTotal-$oldTotal,2);
   $walletQ=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');$walletQ->execute([$seller['id']]);$wallet=$walletQ->fetch();if(!$wallet)throw new RuntimeException('Wallet nicht gefunden.');if((float)$wallet['balance_reserved']+$delta<0)throw new RuntimeException('Die Wallet-Reservierung kann nicht negativ werden.');
   foreach($currentByOffer as $offerOptionId=>$op){if(in_array($offerOptionId,$selected,true))continue;$this->db->prepare('UPDATE order_options SET is_active=0,removed_at=NOW() WHERE id=?')->execute([$op['id']]);$this->db->prepare("INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,status,metadata_json,created_at) VALUES(?,'option_remove',?,?,'active',?,NOW())")->execute([$orderId,'Option vor Start entfernt: '.$op['name'],-(float)$op['price'],json_encode(['order_option_id'=>$op['id'],'actor'=>'seller'],JSON_UNESCAPED_UNICODE)]);}
   foreach($selected as $offerOptionId){if(isset($currentByOffer[$offerOptionId]))continue;$op=$catalog[$offerOptionId];$this->db->prepare('INSERT INTO order_options(order_id,offer_option_id,name,price,config_snapshot,is_active,created_at) VALUES(?,?,?,?,?,1,NOW())')->execute([$orderId,$offerOptionId,$op['name'],$op['price'],$op['requirements_json']]);$orderOptionId=(int)$this->db->lastInsertId();$this->db->prepare("INSERT INTO order_adjustments(order_id,adjustment_type,label,amount,status,metadata_json,created_at) VALUES(?,'option_add',?,?,'active',?,NOW())")->execute([$orderId,'Option vor Start hinzugefügt: '.$op['name'],(float)$op['price'],json_encode(['order_option_id'=>$orderOptionId,'actor'=>'seller'],JSON_UNESCAPED_UNICODE)]);}
   if($delta!=0.0){$this->db->prepare('UPDATE orders SET current_total=current_total+?,updated_at=NOW() WHERE id=?')->execute([$delta,$orderId]);$this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved+?,updated_at=NOW() WHERE id=?')->execute([$delta,$wallet['id']]);$this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'option_change','reserved',?,?,NOW())")->execute([$wallet['id'],$orderId,$delta,json_encode(['actor'=>'seller','before'=>$oldTotal,'after'=>$newTotal],JSON_UNESCAPED_UNICODE)]);}
   $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,?,'options_changed','seller',?,?,NOW())")->execute([$seller['id'],$orderId,$seller['id'],json_encode(['selected'=>$selected,'delta'=>$delta],JSON_UNESCAPED_UNICODE)]);
   $this->db->commit();Session::flash('success','Optionsauswahl wurde aktualisiert.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();Session::flash('error',$e->getMessage());}
  Response::redirect('/konto/auftraege/'.$orderId);
 }
 public function sendChat(Request $r,array $p):void{$s=$this->auth->seller();$q=$this->db->prepare('SELECT c.id,c.is_readonly FROM chats c JOIN orders o ON o.id=c.order_id WHERE o.id=? AND o.seller_id=?');$q->execute([(int)$p['id'],$s['id']]);$c=$q->fetch();if(!$c)Response::abort(404);if($c['is_readonly']){Session::flash('error','Dieser Chat ist schreibgeschützt.');Response::redirect('/konto/auftraege/'.$p['id']);}$msg=trim((string)$r->input('message'));if($msg!=='')$this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,sender_id,message,created_at) VALUES(?,'seller',?,?,NOW())")->execute([$c['id'],$s['id'],$msg]);Response::redirect('/konto/auftraege/'.$p['id']);}
}