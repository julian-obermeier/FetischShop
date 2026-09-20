<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\View;use PDO;
final class SellerController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function dashboard():void{
  $s=$this->auth->seller();$q=$this->db->prepare("SELECT * FROM orders WHERE seller_id=? AND archived_at IS NULL ORDER BY updated_at DESC LIMIT 10");$q->execute([$s['id']]);
  $n=$this->db->prepare("SELECT COUNT(*) FROM notifications WHERE seller_id=? AND read_at IS NULL");$n->execute([$s['id']]);
  $private=$this->db->prepare("SELECT o.id,o.title,o.acceptance_deadline,ov.compensation,c.name category_name FROM offers o JOIN offer_versions ov ON ov.id=o.current_version_id JOIN categories c ON c.id=o.category_id WHERE o.seller_id=? AND o.is_private=1 AND o.status='active' AND o.private_offer_status='pending' AND (o.acceptance_deadline IS NULL OR o.acceptance_deadline>NOW()) ORDER BY o.acceptance_deadline IS NULL,o.acceptance_deadline");$private->execute([$s['id']]);
  View::render($this->root,'seller/dashboard',['pageTitle'=>'Mein Bereich','seller'=>$s,'orders'=>$q->fetchAll(),'unread'=>(int)$n->fetchColumn(),'privateOffers'=>$private->fetchAll()]);
 }
 public function orders():void{$s=$this->auth->seller();$q=$this->db->prepare("SELECT * FROM orders WHERE seller_id=? ORDER BY created_at DESC");$q->execute([$s['id']]);View::render($this->root,'seller/orders',['pageTitle'=>'Meine Aufträge','orders'=>$q->fetchAll()]);}
 public function wallet():void{$s=$this->auth->seller();$w=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=?');$w->execute([$s['id']]);$wallet=$w->fetch();$e=$this->db->prepare('SELECT we.* FROM wallet_entries we JOIN wallets w ON w.id=we.wallet_id WHERE w.seller_id=? ORDER BY we.created_at DESC LIMIT 100');$e->execute([$s['id']]);View::render($this->root,'seller/wallet',['pageTitle'=>'Wallet','wallet'=>$wallet,'entries'=>$e->fetchAll()]);}
 public function notifications():void{$s=$this->auth->seller();$q=$this->db->prepare('SELECT * FROM notifications WHERE seller_id=? ORDER BY created_at DESC LIMIT 100');$q->execute([$s['id']]);$this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE seller_id=?')->execute([$s['id']]);View::render($this->root,'seller/notifications',['pageTitle'=>'Benachrichtigungen','notifications'=>$q->fetchAll()]);}
}