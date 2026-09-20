<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\Mailer;
use PDO;

final class AuthController{
 private array $cfg;
 public function __construct(private string $root,private PDO $db,private Auth $auth){$this->cfg=require $root.'/config/app.php';}
 public function registerForm():void{View::render($this->root,'auth/register',['pageTitle'=>'Registrieren','errors'=>[]]);}
 public function register(Request $r):void{
  $v=(new Validator())->required('first_name',$r->input('first_name'),'Vorname')->required('last_name',$r->input('last_name'),'Nachname')->required('birth_date',$r->input('birth_date'),'Geburtsdatum')->required('street',$r->input('street'),'Straße und Hausnummer')->required('postal_code',$r->input('postal_code'),'PLZ')->required('city',$r->input('city'),'Ort')->required('phone',$r->input('phone'),'Telefonnummer')->required('email',$r->input('email'),'E-Mail')->email('email',$r->input('email'))->min('password',$r->input('password'),10,'Passwort');
  $birth=(string)$r->input('birth_date');$adult=false;try{$b=new \DateTimeImmutable($birth);$adult=$b->diff(new \DateTimeImmutable('today'))->y>=18;}catch(\Throwable){}
  if(!$adult)$errors=['birth_date'=>'Registrierung ist erst ab 18 Jahren möglich.'];else $errors=$v->errors();
  foreach(['adult_confirm','terms','privacy','content_rules','evidence_rules','shipping_rules','wallet_rules'] as $c)if(!$r->input($c))$errors[$c]='Diese Zustimmung ist erforderlich.';
  $email=mb_strtolower(trim((string)$r->input('email')));
  $q=$this->db->prepare('SELECT id FROM sellers WHERE email=? AND deleted_at IS NULL');$q->execute([$email]);if($q->fetch())$errors['email']='Diese E-Mail-Adresse wird bereits verwendet.';
  if($errors){View::render($this->root,'auth/register',['pageTitle'=>'Registrieren','errors'=>$errors]);return;}
  $this->db->beginTransaction();try{
   $s=$this->db->prepare("INSERT INTO sellers(first_name,last_name,birth_date,street,postal_code,city,phone,email,password_hash,accepted_terms_version,accepted_privacy_version,accepted_content_rules_version,accepted_evidence_rules_version,accepted_shipping_rules_version,accepted_wallet_rules_version,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
   $ver='2026-09-20';$s->execute([trim((string)$r->input('first_name')),trim((string)$r->input('last_name')),$birth,trim((string)$r->input('street')),trim((string)$r->input('postal_code')),trim((string)$r->input('city')),trim((string)$r->input('phone')),$email,password_hash((string)$r->input('password'),PASSWORD_DEFAULT),$ver,$ver,$ver,$ver,$ver,$ver]);
   $id=(int)$this->db->lastInsertId();$this->db->prepare('INSERT INTO wallets(seller_id,updated_at) VALUES(?,NOW())')->execute([$id]);$this->db->commit();
   $seller=['id'=>$id];$this->auth->loginSeller($seller);$this->sendVerification($id,$email);
   Session::flash('success','Registrierung erfolgreich. Bitte bestätige jetzt deine E-Mail-Adresse.');Response::redirect('/konto/email-bestaetigen');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 private function sendVerification(int $sellerId,string $email):void{
  $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
  $this->db->prepare('DELETE FROM email_verifications WHERE seller_id=? AND used_at IS NULL')->execute([$sellerId]);
  $this->db->prepare('INSERT INTO email_verifications(seller_id,token_hash,email_snapshot,expires_at,created_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW())')->execute([$sellerId,$hash,$email]);
  $base=rtrim((string)($this->cfg['base_url']??''),'/');$url=$base.'/email-verifizieren/'.$token;
  (new Mailer($this->cfg))->send($email,'E-Mail-Adresse bestätigen','<p>Bitte bestätige deine E-Mail-Adresse:</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">E-Mail bestätigen</a></p><p>Der Link ist 24 Stunden gültig.</p>');
 }
 public function verifyEmail(Request $r,array $p):void{
  $hash=hash('sha256',(string)$p['token']);$q=$this->db->prepare('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([$hash]);$v=$q->fetch();if(!$v){Session::flash('error','Der Bestätigungslink ist ungültig oder abgelaufen.');Response::redirect('/login');}
  $this->db->beginTransaction();try{$this->db->prepare('UPDATE sellers SET email_verified_at=NOW(),updated_at=NOW() WHERE id=? AND email=?')->execute([$v['seller_id'],$v['email_snapshot']]);$this->db->prepare('UPDATE email_verifications SET used_at=NOW() WHERE id=?')->execute([$v['id']]);$this->db->commit();Session::flash('success','E-Mail-Adresse erfolgreich bestätigt.');Response::redirect('/konto');}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function verificationNotice():void{View::render($this->root,'auth/verify-notice',['pageTitle'=>'E-Mail bestätigen']);}
 public function resendVerification():void{$s=$this->auth->seller();if($s&&!$s['email_verified_at'])$this->sendVerification((int)$s['id'],$s['email']);Session::flash('success','Falls noch erforderlich, wurde ein neuer Bestätigungslink versendet.');Response::redirect('/konto/email-bestaetigen');}
 public function loginForm():void{View::render($this->root,'auth/login',['pageTitle'=>'Login']);}
 public function login(Request $r):void{
  $email=mb_strtolower(trim((string)$r->input('email')));$key='seller-login:'.$r->ip().':'.$email;$lim=new RateLimiter($this->db);if($lim->tooMany($key,6,900)){Session::flash('error','Zu viele Anmeldeversuche. Bitte später erneut versuchen.');Response::redirect('/login');}
  $q=$this->db->prepare('SELECT * FROM sellers WHERE email=? AND deleted_at IS NULL LIMIT 1');$q->execute([$email]);$s=$q->fetch();
  if(!$s||!password_verify((string)$r->input('password'),$s['password_hash'])){$lim->hit($key);Session::flash('error','E-Mail-Adresse oder Passwort ist falsch.');Response::redirect('/login');}
  $lim->clear($key);if(password_needs_rehash($s['password_hash'],PASSWORD_DEFAULT)){$this->db->prepare('UPDATE sellers SET password_hash=? WHERE id=?')->execute([password_hash((string)$r->input('password'),PASSWORD_DEFAULT),$s['id']]);}
  $this->auth->loginSeller($s);Response::redirect('/konto');
 }
 public function logout():void{$this->auth->logoutSeller();Response::redirect('/');}
 public function forgotForm():void{View::render($this->root,'auth/forgot',['pageTitle'=>'Passwort vergessen']);}
 public function forgot(Request $r):void{
  $email=mb_strtolower(trim((string)$r->input('email')));$key='forgot:'.$r->ip();$lim=new RateLimiter($this->db);if(!$lim->tooMany($key,4,3600)){$lim->hit($key);$q=$this->db->prepare('SELECT id,email FROM sellers WHERE email=? AND deleted_at IS NULL');$q->execute([$email]);if($s=$q->fetch()){$token=bin2hex(random_bytes(32));$this->db->prepare('INSERT INTO password_resets(seller_id,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW())')->execute([$s['id'],hash('sha256',$token)]);$url=rtrim((string)$this->cfg['base_url'],'/').'/passwort-zuruecksetzen/'.$token;(new Mailer($this->cfg))->send($s['email'],'Passwort zurücksetzen','<p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Neues Passwort festlegen</a></p><p>Der Link ist 30 Minuten gültig und nur einmal verwendbar.</p>');}}
  Session::flash('success','Wenn ein Konto mit dieser E-Mail existiert, wurde ein Resetlink versendet.');Response::redirect('/passwort-vergessen');
 }
 public function resetForm(Request $r,array $p):void{View::render($this->root,'auth/reset',['pageTitle'=>'Passwort zurücksetzen','token'=>$p['token']]);}
 public function reset(Request $r,array $p):void{
  $password=(string)$r->input('password');if(strlen($password)<10){Session::flash('error','Das Passwort muss mindestens 10 Zeichen lang sein.');Response::redirect('/passwort-zuruecksetzen/'.$p['token']);}
  $q=$this->db->prepare('SELECT * FROM password_resets WHERE token_hash=? AND seller_id IS NOT NULL AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([hash('sha256',(string)$p['token'])]);$x=$q->fetch();if(!$x){Session::flash('error','Der Resetlink ist ungültig oder abgelaufen.');Response::redirect('/passwort-vergessen');}
  $this->db->beginTransaction();try{$this->db->prepare('UPDATE sellers SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$x['seller_id']]);$this->db->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$x['id']]);$this->db->commit();Session::flash('success','Passwort wurde geändert.');Response::redirect('/login');}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}