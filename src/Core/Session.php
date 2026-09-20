<?php
namespace App\Core;
final class Session {
 public static function start():void{
  if(session_status()===PHP_SESSION_ACTIVE)return;
  $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
  session_name('fetischshop_session');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
  if(!isset($_SESSION['_started_at'])){session_regenerate_id(true);$_SESSION['_started_at']=time();}
 }
 public static function get(string $k,mixed $d=null):mixed{return $_SESSION[$k]??$d;}
 public static function put(string $k,mixed $v):void{$_SESSION[$k]=$v;}
 public static function forget(string $k):void{unset($_SESSION[$k]);}
 public static function flash(string $k,mixed $v):void{$_SESSION['_flash'][$k]=$v;}
 public static function pullFlash(string $k,mixed $d=null):mixed{$v=$_SESSION['_flash'][$k]??$d;unset($_SESSION['_flash'][$k]);return $v;}
 public static function regenerate():void{session_regenerate_id(true);}
 public static function destroy():void{$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();}
}