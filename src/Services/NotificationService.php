<?php
namespace App\Services;
use PDO;
final class NotificationService{
 public function __construct(private PDO $db,private Mailer $mailer){}
 public function seller(int $sellerId,string $type,string $title,string $message,?string $url=null,bool $email=true):void{$q=$this->db->prepare('INSERT INTO notifications(seller_id,type,title,message,url,created_at) VALUES(?,?,?,?,?,NOW())');$q->execute([$sellerId,$type,$title,$message,$url]);if($email){$s=$this->db->prepare('SELECT email FROM sellers WHERE id=?');$s->execute([$sellerId]);if($addr=$s->fetchColumn())$this->mailer->send((string)$addr,$title,'<p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>');}}
}