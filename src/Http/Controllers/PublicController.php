<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use PDO;

final class PublicController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function home():void{
  $offers=$this->db->query("SELECT o.id,o.title,c.name category_name,ov.compensation,ov.duration_value,ov.duration_unit FROM offers o JOIN categories c ON c.id=o.category_id JOIN offer_versions ov ON ov.id=o.current_version_id WHERE o.status='active' AND o.is_private=0 ORDER BY o.updated_at DESC LIMIT 6")->fetchAll();
  View::render($this->root,'public/home',['pageTitle'=>'FetischShop – diskrete Ankaufsplattform','offers'=>$offers]);
 }
 public function offers(Request $r):void{
  $where=["o.status='active'","o.is_private=0"]; $params=[];
  if($q=trim((string)$r->input('q'))){$where[]='(o.title LIKE ? OR ov.description LIKE ? OR c.name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
  if($cat=(int)$r->input('category')){$where[]='o.category_id=?';$params[]=$cat;}
  if(($min=$r->input('min'))!==null&&$min!==''){$where[]='ov.compensation>=?';$params[]=(float)$min;}
  if(($max=$r->input('max'))!==null&&$max!==''){$where[]='ov.compensation<=?';$params[]=(float)$max;}
  $sql="SELECT o.id,o.title,o.category_id,c.name category_name,c.icon,ov.compensation,ov.duration_value,ov.duration_unit,ov.description FROM offers o JOIN categories c ON c.id=o.category_id JOIN offer_versions ov ON ov.id=o.current_version_id WHERE ".implode(' AND ',$where)." ORDER BY o.updated_at DESC";
  $s=$this->db->prepare($sql);$s->execute($params);
  $cats=$this->db->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,name")->fetchAll();
  View::render($this->root,'public/offers',['pageTitle'=>'Angebote','offers'=>$s->fetchAll(),'categories'=>$cats]);
 }
 public function offer(Request $r,array $p):void{
  $s=$this->db->prepare("SELECT o.*,c.name category_name,c.is_digital,ov.* FROM offers o JOIN categories c ON c.id=o.category_id JOIN offer_versions ov ON ov.id=o.current_version_id WHERE o.id=? AND o.status='active' AND o.is_private=0");
  $s->execute([(int)$p['id']]);$offer=$s->fetch();if(!$offer)Response::abort(404,'Angebot nicht gefunden.');
  $opt=$this->db->prepare("SELECT * FROM offer_options WHERE offer_version_id=? AND is_active=1 ORDER BY sort_order,id");$opt->execute([$offer['current_version_id']]);
  View::render($this->root,'public/offer',['pageTitle'=>$offer['title'],'offer'=>$offer,'options'=>$opt->fetchAll()]);
 }
 public function howItWorks():void{View::render($this->root,'public/how',['pageTitle'=>'So funktioniert es']);}
 public function faq():void{View::render($this->root,'public/faq',['pageTitle'=>'FAQ']);}
 public function contact():void{View::render($this->root,'public/contact',['pageTitle'=>'Kontakt']);}
 public function imprint():void{View::render($this->root,'legal/imprint',['pageTitle'=>'Impressum']);}
 public function privacy():void{View::render($this->root,'legal/privacy',['pageTitle'=>'Datenschutz']);}
 public function terms():void{View::render($this->root,'legal/terms',['pageTitle'=>'Bedingungen']);}
}