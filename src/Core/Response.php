<?php
namespace App\Core;
final class Response{
 public static function redirect(string $to,int $status=302):never{header('Location: '.$to,true,$status);exit;}
 public static function json(array $d,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
 public static function abort(int $status,string $message=''):never{http_response_code($status);echo htmlspecialchars($message?:'Fehler '.$status,ENT_QUOTES,'UTF-8');exit;}
}