<?php
namespace App\Core;
final class Csrf{
 public static function token():string{$t=Session::get('_csrf');if(!$t){$t=bin2hex(random_bytes(32));Session::put('_csrf',$t);}return $t;}
 public static function field():string{return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(self::token(),ENT_QUOTES,'UTF-8').'">';}
 public static function verify(?string $t):bool{return is_string($t)&&hash_equals(self::token(),$t);}
}