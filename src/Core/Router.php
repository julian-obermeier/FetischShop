<?php
namespace App\Core;
final class Router{
 private array $routes=[];
 public function add(string $m,string $p,callable $h,array $mw=[]):void{$this->routes[]=[strtoupper($m),$p,$h,$mw];}
 public function get(string $p,callable $h,array $mw=[]):void{$this->add('GET',$p,$h,$mw);}
 public function post(string $p,callable $h,array $mw=[]):void{$this->add('POST',$p,$h,$mw);}
 public function dispatch(Request $r):void{
  foreach($this->routes as [$m,$p,$h,$mw]){
   if($m!==$r->method)continue;
   $regex=preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[^/]+)',$p);
   if(!preg_match('#^'.$regex.'$#',$r->path,$matches))continue;
   $params=array_filter($matches,'is_string',ARRAY_FILTER_USE_KEY);
   foreach($mw as $fn)$fn($r,$params);
   $h($r,$params);return;
  }
  Response::abort(404,'Seite nicht gefunden.');
 }
}