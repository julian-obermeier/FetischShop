<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Response;use PDO;
final class MediaController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function evidence($r,array $p):void{
  $id=(int)$p['id'];$q=$this->db->prepare('SELECT e.*,o.seller_id FROM evidences e JOIN orders o ON o.id=e.order_id WHERE e.id=?');$q->execute([$id]);$e=$q->fetch();if(!$e)Response::abort(404);
  $seller=$this->auth->seller();$admin=$this->auth->admin();if(!$admin&&(!$seller||(int)$seller['id']!==(int)$e['seller_id']))Response::abort(403);
  if(!is_file($e['file_path']))Response::abort(404);
  header('Content-Type: '.$e['mime_type']);header('Content-Length: '.filesize($e['file_path']));header('Content-Disposition: inline; filename="nachweis-'.$id.'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($e['file_path']);exit;
 }
}