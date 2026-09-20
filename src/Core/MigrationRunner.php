<?php
namespace App\Core;
use PDO;
final class MigrationRunner{
 public function __construct(private PDO $db,private string $root){}
 public function ensureTable():void{$this->db->exec("CREATE TABLE IF NOT EXISTS migrations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration VARCHAR(190) NOT NULL UNIQUE,executed_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
 public function pending():array{$this->ensureTable();$done=$this->db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);$files=glob($this->root.'/database/migrations/*.sql')?:[];sort($files);return array_values(array_filter($files,fn($x)=>!in_array(basename($x),$done,true)));}
 public function history():array{$this->ensureTable();return $this->db->query('SELECT * FROM migrations ORDER BY id DESC')->fetchAll();}
 public function run():array{
  $done=[];
  foreach($this->pending() as $file){
   $sql=(string)file_get_contents($file);
   foreach($this->statements($sql) as $statement)$this->db->exec($statement);
   $q=$this->db->prepare('INSERT INTO migrations(migration,executed_at) VALUES(?,NOW())');$q->execute([basename($file)]);
   $done[]=basename($file);
  }
  return $done;
 }
 private function statements(string $sql):array{
  $sql=preg_replace('/^\s*--.*$/m','',$sql);
  $out=[];$buf='';$quote=null;$escape=false;$len=strlen($sql);
  for($i=0;$i<$len;$i++){
   $ch=$sql[$i];
   if($escape){$buf.=$ch;$escape=false;continue;}
   if($ch==='\\'&&$quote!==null){$buf.=$ch;$escape=true;continue;}
   if(($ch==="'"||$ch==='""')&&($quote===null||$quote===$ch)){$quote=$quote===null?$ch:null;$buf.=$ch;continue;}
   if($ch===';'&&$quote===null){if(trim($buf)!=='')$out[]=trim($buf);$buf='';continue;}
   $buf.=$ch;
  }
  if(trim($buf)!=='')$out[]=trim($buf);
  return $out;
 }
}