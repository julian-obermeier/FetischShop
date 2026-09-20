<?php
namespace App\Services;
use RuntimeException;
final class PrivateStorage{
 public function __construct(private string $root){}
 public function storeUploaded(array $file,string $bucket,array $allowedMime,int $maxBytes):array{
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Upload fehlgeschlagen.');
  if((int)$file['size']>$maxBytes)throw new RuntimeException('Datei ist zu groß.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  if(!in_array($mime,$allowedMime,true))throw new RuntimeException('Dateityp ist nicht erlaubt.');
  $dir=$this->root.'/'.$bucket.'/'.date('Y/m');if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Speicherverzeichnis konnte nicht erstellt werden.');
  $path=$dir.'/'.bin2hex(random_bytes(24)).'.bin';if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Datei konnte nicht gespeichert werden.');
  return ['path'=>$path,'mime'=>$mime,'size'=>(int)filesize($path),'sha256'=>hash_file('sha256',$path),'original_name'=>(string)($file['name']??'datei')];
 }
}