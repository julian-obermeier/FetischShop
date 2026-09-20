<?php
namespace App\Services;
use PDO;
final class OrderNumberService{
 public function __construct(private PDO $db){}
 public function next():string{$year=(int)date('Y');$this->db->beginTransaction();try{$q=$this->db->prepare('SELECT sequence FROM order_sequences WHERE year=? FOR UPDATE');$q->execute([$year]);$seq=$q->fetchColumn();if($seq===false){$seq=1;$i=$this->db->prepare('INSERT INTO order_sequences(year,sequence) VALUES(?,?)');$i->execute([$year,$seq]);}else{$seq=(int)$seq+1;$u=$this->db->prepare('UPDATE order_sequences SET sequence=? WHERE year=?');$u->execute([$seq,$year]);}$this->db->commit();return sprintf('%04d%04d',$year,$seq);}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
}