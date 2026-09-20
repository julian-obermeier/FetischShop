<?php
namespace App\Core;
final class Logger{
 public static function error(string $root,string $message,array $context=[]):void{
  $dir=$root.'/storage/logs';if(!is_dir($dir))@mkdir($dir,0770,true);
  $line='['.date('c').'] ERROR '.$message.($context?' '.json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'');
  @file_put_contents($dir.'/app.log',$line.PHP_EOL,FILE_APPEND|LOCK_EX);
 }
}