<?php
namespace App\Services;
use PDO;
final class OrderNumberService{
 public function __construct(private PDO $db){}
 public function next():string{
  $year=(int)date('Y');$ownTx=!$this->db->inTransaction();if($ownTx)$this->db->beginTransaction();
  try{
   $q=$this->db->prepare('SELECT sequence FROM order_sequences WHERE year=? FOR UPDATE');$q->execute([$year]);$seq=$q->fetchColumn();
   if($seq===false){$seq=1;$this->db->prepare('INSERT INTO order_sequences(year,sequence) VALUES(?,?)')->execute([$year,$seq]);}
   else{$seq=(int)$seq+1;$this->db->prepare('UPDATE order_sequences SET sequence=? WHERE year=?')->execute([$seq,$year]);}
   if($ownTx)$this->db->commit();return sprintf('%04d%04d',$year,$seq);
  }catch(\Throwable $e){if($ownTx&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}