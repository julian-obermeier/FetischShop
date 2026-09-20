<?php
namespace App\Core;
final class Request{
 public function __construct(public readonly string $method,public readonly string $path,public readonly array $query,public readonly array $body,public readonly array $files,public readonly array $server){}
 public static function capture():self{$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$path='/'.trim($uri,'/');if($path!=='/')$path=rtrim($path,'/');$method=strtoupper($_POST['_method']??$_SERVER['REQUEST_METHOD']??'GET');return new self($method,$path,$_GET,$_POST,$_FILES,$_SERVER);}
 public function input(string $k,mixed $d=null):mixed{return $this->body[$k]??$this->query[$k]??$d;}
 public function isPost():bool{return $this->method==='POST';}
 public function ip():string{return (string)($this->server['REMOTE_ADDR']??'0.0.0.0');}
}