<?php
namespace App\Core;
use PDO;
final class MigrationRunner{
 public function __construct(private PDO $db,private string $root){}
 public function ensureTable():void{$this->db->exec("CREATE TABLE IF NOT EXISTS migrations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration VARCHAR(190) NOT NULL UNIQUE,executed_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
 public function pending():array{$this->ensureTable();$done=$this->db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);$files=glob($this->root.'/database/migrations/*.sql')?:[];sort($files);return array_values(array_filter($files,fn($f)=>!in_array(basename($f),$done,true)));}
 public function run():array{$done=[];foreach($this->pending() as $f){$this->db->beginTransaction();try{$this->db->exec(file_get_contents($f));$q=$this->db->prepare('INSERT INTO migrations(migration,executed_at) VALUES(?,NOW())');$q->execute([basename($f)]);$this->db->commit();$done[]=basename($f);}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}return $done;}
}