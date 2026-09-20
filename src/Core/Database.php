<?php
namespace App\Core;
use PDO;use RuntimeException;use DateTimeImmutable;use DateTimeZone;
final class Database{
 private static ?PDO $pdo=null;
 public static function configured(string $root):bool{return is_file($root.'/config/database.php');}
 public static function connection(string $root):PDO{
  if(self::$pdo)return self::$pdo;
  $file=$root.'/config/database.php';if(!is_file($file))throw new RuntimeException('Datenbank ist noch nicht konfiguriert.');
  $c=require $file;
  self::$pdo=new PDO($c['dsn'],$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
  $offset=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('P');
  $quoted=self::$pdo->quote($offset);self::$pdo->exec("SET time_zone=".$quoted);
  return self::$pdo;
 }
}