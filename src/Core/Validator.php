<?php
namespace App\Core;
final class Validator{
 private array $errors=[];
 public function required(string $f,mixed $v,string $l):self{if($v===null||trim((string)$v)==='')$this->errors[$f]="$l ist erforderlich.";return $this;}
 public function email(string $f,mixed $v):self{if($v&&!filter_var($v,FILTER_VALIDATE_EMAIL))$this->errors[$f]='Ungültige E-Mail-Adresse.';return $this;}
 public function min(string $f,mixed $v,int $n,string $l):self{if(mb_strlen((string)$v)<$n)$this->errors[$f]="$l muss mindestens $n Zeichen enthalten.";return $this;}
 public function errors():array{return $this->errors;}
 public function passes():bool{return $this->errors===[];}
}