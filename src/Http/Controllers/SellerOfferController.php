<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use PDO;
final class SellerOfferController{
 public function __construct(private PDO $db,private Auth $auth){}
 public function decline(Request $r,array $p):void{
  $seller=$this->auth->seller();$reason=trim((string)$r->input('reason'));if($reason===''){Session::flash('error','Bitte gib einen Ablehnungsgrund an.');Response::redirect('/angebote/'.$p['id']);}
  $q=$this->db->prepare("UPDATE offers SET private_offer_status='declined',declined_reason=?,updated_at=NOW() WHERE id=? AND seller_id=? AND is_private=1 AND status='active' AND private_offer_status='pending' AND (acceptance_deadline IS NULL OR acceptance_deadline>NOW())");
  $q->execute([$reason,(int)$p['id'],$seller['id']]);if($q->rowCount()!==1){Session::flash('error','Dieses Privatangebot kann nicht mehr abgelehnt werden.');Response::redirect('/konto');}
  try{$this->db->prepare('DELETE FROM cart_items WHERE seller_id=? AND offer_id=?')->execute([$seller['id'],(int)$p['id']]);}catch(\Throwable){}
  $this->db->prepare("INSERT INTO system_events(seller_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,'private_offer_declined','seller',?,?,NOW())")->execute([$seller['id'],$seller['id'],json_encode(['offer_id'=>(int)$p['id'],'reason'=>$reason],JSON_UNESCAPED_UNICODE)]);
  Session::flash('success','Privatangebot wurde abgelehnt und aus dem Warenkorb entfernt.');Response::redirect('/konto');
 }
}