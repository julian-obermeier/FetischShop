<?php
namespace App\Http\Controllers;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Core\View;use App\Services\Mailer;use PDO;
final class SellerProfileController{
 private array $cfg;
 public function __construct(private string $root,private PDO $db,private Auth $auth){$this->cfg=require $root.'/config/app.php';}
 public function show():void{View::render($this->root,'seller/profile',['pageTitle'=>'Mein Profil','seller'=>$this->auth->seller()]);}
 public function update(Request $r):void{
  $s=$this->auth->seller();$fields=['first_name','last_name','street','postal_code','city','phone'];$this->db->beginTransaction();try{
   foreach($fields as $f){$new=trim((string)$r->input($f));if($new===''||$new===$s[$f])continue;$this->log((int)$s['id'],$f,(string)$s[$f],$new);}
   $q=$this->db->prepare('UPDATE sellers SET first_name=?,last_name=?,street=?,postal_code=?,city=?,phone=?,updated_at=NOW() WHERE id=?');$q->execute([trim((string)$r->input('first_name')),trim((string)$r->input('last_name')),trim((string)$r->input('street')),trim((string)$r->input('postal_code')),trim((string)$r->input('city')),trim((string)$r->input('phone')),$s['id']]);$this->db->commit();Session::flash('success','Profildaten wurden aktualisiert.');
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}Response::redirect('/konto/profil');
 }
 public function changeEmail(Request $r):void{
  $s=$this->auth->seller();$email=mb_strtolower(trim((string)$r->input('email')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){Session::flash('error','Ungültige E-Mail-Adresse.');Response::redirect('/konto/profil');}
  $q=$this->db->prepare('SELECT id FROM sellers WHERE email=? AND id<>? AND deleted_at IS NULL');$q->execute([$email,$s['id']]);if($q->fetch()){Session::flash('error','Diese E-Mail-Adresse ist bereits vergeben.');Response::redirect('/konto/profil');}
  if($email===$s['email'])Response::redirect('/konto/profil');
  $this->db->beginTransaction();try{$this->log((int)$s['id'],'email',(string)$s['email'],$email);$this->db->prepare('UPDATE sellers SET email=?,email_verified_at=NULL,updated_at=NOW() WHERE id=?')->execute([$email,$s['id']]);$token=bin2hex(random_bytes(32));$this->db->prepare('DELETE FROM email_verifications WHERE seller_id=? AND used_at IS NULL')->execute([$s['id']]);$this->db->prepare('INSERT INTO email_verifications(seller_id,token_hash,email_snapshot,expires_at,created_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW())')->execute([$s['id'],hash('sha256',$token),$email]);$this->db->commit();$url=rtrim((string)$this->cfg['base_url'],'/').'/email-verifizieren/'.$token;(new Mailer($this->cfg))->send($email,'Neue E-Mail-Adresse bestätigen','<p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">E-Mail-Adresse bestätigen</a></p>');Session::flash('success','Neue E-Mail gespeichert. Bitte bestätige sie über den versendeten Link.');}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}Response::redirect('/konto/profil');
 }
 private function log(int $id,string $field,string $old,string $new):void{$this->db->prepare('INSERT INTO seller_data_changes(seller_id,field_name,old_value,new_value,changed_at) VALUES(?,?,?,?,NOW())')->execute([$id,$field,$old,$new]);}
}