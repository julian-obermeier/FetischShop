<?php
namespace App\Core;
use PDO;
final class Auth{
 public function __construct(private PDO $db){}
 public function seller():?array{$id=Session::get('seller_id');if(!$id)return null;$q=$this->db->prepare('SELECT * FROM sellers WHERE id=? AND deleted_at IS NULL LIMIT 1');$q->execute([$id]);return $q->fetch()?:null;}
 public function admin():?array{$id=Session::get('admin_id');if(!$id)return null;$q=$this->db->prepare('SELECT * FROM admins WHERE id=? LIMIT 1');$q->execute([$id]);return $q->fetch()?:null;}
 public function loginSeller(array $s):void{Session::regenerate();Session::put('seller_id',(int)$s['id']);}
 public function loginAdmin(array $a):void{Session::regenerate();Session::put('admin_id',(int)$a['id']);}
 public function logoutSeller():void{Session::forget('seller_id');Session::regenerate();}
 public function logoutAdmin():void{Session::forget('admin_id');Session::regenerate();}
}