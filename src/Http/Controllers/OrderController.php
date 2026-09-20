<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use App\Services\OrderService;use App\Services\PrivateStorage;use PDO;use RuntimeException;
final class OrderController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function acceptOffer(Request $r,array $p):void{
  $s=$this->auth->seller();try{$options=$r->input('options',[]);if(!is_array($options))$options=[];$id=(new OrderService($this->db))->accept((int)$s['id'],(int)$p['id'],$options,(bool)$r->input('rights_acceptance'));Session::flash('success','Auftrag wurde angenommen und für die Vorbereitung angelegt.');Response::redirect('/konto/auftraege/'.$id);}catch(\Throwable $e){Session::flash('error',$e->getMessage());Response::redirect('/angebote/'.$p['id']);}
 }
 public function show(Request $r,array $p):void{
  $s=$this->auth->seller();$q=$this->db->prepare("SELECT o.*,ov.title,ov.description,ov.duration_value,ov.duration_unit,ov.start_control_json,ov.evidence_json,c.name category_name,c.is_digital FROM orders o JOIN offer_versions ov ON ov.id=o.offer_version_id JOIN offers off ON off.id=o.offer_id JOIN categories c ON c.id=off.category_id WHERE o.id=? AND o.seller_id=?");$q->execute([(int)$p['id'],$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);
  $run=$this->db->prepare('SELECT * FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$run=$run->fetch();
  $item=$this->db->prepare('SELECT * FROM order_items WHERE order_id=? AND order_run_id=? ORDER BY id DESC LIMIT 1');$item->execute([$o['id'],$run['id']]);$item=$item->fetch();
  $ev=$this->db->prepare('SELECT e.*,ew.name window_name FROM evidences e LEFT JOIN evidence_windows ew ON ew.id=e.evidence_window_id WHERE e.order_id=? ORDER BY e.created_at DESC');$ev->execute([$o['id']]);
  $days=$this->db->prepare('SELECT od.*, (SELECT COUNT(*) FROM evidence_windows ew WHERE ew.order_day_id=od.id) window_count FROM order_days od WHERE od.order_id=? ORDER BY calendar_date,COALESCE(day_no,0)');$days->execute([$o['id']]);
  $windows=$this->db->prepare("SELECT ew.*,od.day_no,od.calendar_date,(SELECT COUNT(*) FROM evidences e WHERE e.evidence_window_id=ew.id) uploaded_count FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE od.order_id=? ORDER BY ew.starts_at");$windows->execute([$o['id']]);
  $viol=$this->db->prepare('SELECT * FROM violations WHERE order_id=? ORDER BY created_at DESC');$viol->execute([$o['id']]);
  $chat=$this->db->prepare('SELECT cm.* FROM chat_messages cm JOIN chats c ON c.id=cm.chat_id WHERE c.order_id=? ORDER BY cm.created_at');$chat->execute([$o['id']]);
  View::render($this->root,'seller/order',['pageTitle'=>'Auftrag #'.$o['order_number'],'order'=>$o,'run'=>$run,'item'=>$item,'evidences'=>$ev->fetchAll(),'days'=>$days->fetchAll(),'windows'=>$windows->fetchAll(),'violations'=>$viol->fetchAll(),'messages'=>$chat->fetchAll()]);
 }
 public function saveItem(Request $r,array $p):void{
  $s=$this->auth->seller();$q=$this->db->prepare("SELECT o.*,off.category_id FROM orders o JOIN offers off ON off.id=o.offer_id WHERE o.id=? AND o.seller_id=?");$q->execute([(int)$p['id'],$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);if($o['started_at']){Session::flash('error','Nach Auftragsstart ist kein normaler Artikelwechsel mehr möglich.');Response::redirect('/konto/auftraege/'.$o['id']);}
  $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$rid=(int)$run->fetchColumn();$this->db->prepare('DELETE FROM order_items WHERE order_id=? AND order_run_id=?')->execute([$o['id'],$rid]);
  $this->db->prepare('INSERT INTO order_items(order_id,order_run_id,category_id,short_name,size,color,brand,material,attributes_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())')->execute([$o['id'],$rid,$o['category_id'],trim((string)$r->input('short_name')),trim((string)$r->input('size'))?:null,trim((string)$r->input('color'))?:null,trim((string)$r->input('brand'))?:null,trim((string)$r->input('material'))?:null,'{}']);Session::flash('success','Artikelangaben gespeichert.');Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function uploadEvidence(Request $r,array $p):void{
  $s=$this->auth->seller();$q=$this->db->prepare('SELECT * FROM orders WHERE id=? AND seller_id=?');$q->execute([(int)$p['id'],$s['id']]);$o=$q->fetch();if(!$o)Response::abort(404);
  try{
   $type=(string)$r->input('evidence_type','regular');$windowId=$r->input('evidence_window_id')?(int)$r->input('evidence_window_id'):null;
   if($type==='precheck'){if($o['started_at'])throw new RuntimeException('Vorabnachweise können nach Start nicht mehr ergänzt werden.');$it=$this->db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=?');$it->execute([$o['id']]);if((int)$it->fetchColumn()<1)throw new RuntimeException('Bitte zuerst den konkreten Artikel erfassen.');}
   else{if(!$windowId)throw new RuntimeException('Nachweisfenster fehlt.');$w=$this->db->prepare('SELECT ew.* FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE ew.id=? AND od.order_id=?');$w->execute([$windowId,$o['id']]);$win=$w->fetch();if(!$win)throw new RuntimeException('Nachweisfenster ist ungültig.');$now=time();if($now<strtotime($win['starts_at']))throw new RuntimeException('Das Nachweisfenster hat noch nicht begonnen.');if($now>strtotime($win['grace_ends_at']))throw new RuntimeException('Die Nachfrist ist abgelaufen.');}
   $cfg=require $this->root.'/config/app.php';$file=(new PrivateStorage($cfg['private_storage']))->storeUploaded($r->files['evidence']??[],'evidence',['image/jpeg','image/png','image/webp'],12*1024*1024);
   $run=$this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');$run->execute([$o['id']]);$rid=(int)$run->fetchColumn();
   $this->db->prepare("INSERT INTO evidences(order_id,order_run_id,evidence_window_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),?,'pending',NOW())")->execute([$o['id'],$rid,$windowId,$type,$file['path'],$file['original_name'],$file['mime'],$file['size'],$file['sha256'],json_encode(['user_agent'=>$r->server['HTTP_USER_AGENT']??null],JSON_UNESCAPED_UNICODE)]);
   Session::flash('success','Nachweis wurde unverändert gespeichert und zur Prüfung eingereicht.');
  }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
  Response::redirect('/konto/auftraege/'.$o['id']);
 }
 public function sendChat(Request $r,array $p):void{$s=$this->auth->seller();$q=$this->db->prepare('SELECT c.id,c.is_readonly FROM chats c JOIN orders o ON o.id=c.order_id WHERE o.id=? AND o.seller_id=?');$q->execute([(int)$p['id'],$s['id']]);$c=$q->fetch();if(!$c)Response::abort(404);if($c['is_readonly']){Session::flash('error','Dieser Chat ist schreibgeschützt.');Response::redirect('/konto/auftraege/'.$p['id']);}$msg=trim((string)$r->input('message'));if($msg!=='')$this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,sender_id,message,created_at) VALUES(?,'seller',?,?,NOW())")->execute([$c['id'],$s['id'],$msg]);Response::redirect('/konto/auftraege/'.$p['id']);}
}