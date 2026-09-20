<?php
namespace App\Core;
use PDO;
final class RateLimiter{
 public function __construct(private PDO $db){}
 public function tooMany(string $key,int $max,int $window):bool{$cut=date('Y-m-d H:i:s',time()-$window);$q=$this->db->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket_key=? AND created_at>=?');$q->execute([$key,$cut]);return (int)$q->fetchColumn()>=$max;}
 public function hit(string $key):void{$q=$this->db->prepare('INSERT INTO rate_limits(bucket_key,created_at) VALUES(?,NOW())');$q->execute([$key]);}
 public function clear(string $key):void{$q=$this->db->prepare('DELETE FROM rate_limits WHERE bucket_key=?');$q->execute([$key]);}
}