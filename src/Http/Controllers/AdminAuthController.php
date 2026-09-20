<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\RateLimiter;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use PDO;
final class AdminAuthController{
 public function __construct(private string $root,private PDO $db,private Auth $auth){}
 public function loginForm():void{View::render($this->root,'admin/login',['pageTitle'=>'Admin Login']);}
 public function login(Request $r):void{
  $email=mb_strtolower(trim((string)$r->input('email')));$key='admin-login:'.$r->ip().':'.$email;$lim=new RateLimiter($this->db);
  if($lim->tooMany($key,5,900)){Session::flash('error','Zu viele Anmeldeversuche.');Response::redirect('/admin/login');}
  $q=$this->db->prepare('SELECT * FROM admins WHERE email=? LIMIT 1');$q->execute([$email]);$a=$q->fetch();
  if(!$a||!password_verify((string)$r->input('password'),$a['password_hash'])){$lim->hit($key);Session::flash('error','Anmeldedaten sind falsch.');Response::redirect('/admin/login');}
  $lim->clear($key);if(password_needs_rehash($a['password_hash'],PASSWORD_DEFAULT)){$this->db->prepare('UPDATE admins SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash((string)$r->input('password'),PASSWORD_DEFAULT),$a['id']]);}$this->auth->loginAdmin($a);Response::redirect('/admin');
 }
 public function logout():void{$this->auth->logoutAdmin();Response::redirect('/admin/login');}
}