<?php
namespace App\Services;
final class Mailer{
 public function __construct(private array $config){}
 public function send(string $to,string $subject,string $html):bool{$h=['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: '.$this->config['mail_from_name'].' <'.$this->config['mail_from'].'>','X-Mailer: FetischShop'];return mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$html,implode("\r\n",$h));}
}