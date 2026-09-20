<?php
namespace App\Core;
use RuntimeException;
final class View{
 public static function render(string $root,string $view,array $data=[]):void{$file=$root.'/views/'.$view.'.php';if(!is_file($file))throw new RuntimeException('View nicht gefunden: '.$view);extract($data,EXTR_SKIP);$contentView=$file;require $root.'/views/layout.php';}
 public static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
}