<?php
namespace App\Services;
use RuntimeException;
final class PrivateStorage{
 public function __construct(private string $root){}
 public function storeUploaded(array $file,string $bucket,array $allowedMime,int $maxBytes,int $minImageSide=0):array{
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Upload fehlgeschlagen.');
  if((int)$file['size']>$maxBytes)throw new RuntimeException('Datei ist zu groß.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  if(!in_array($mime,$allowedMime,true))throw new RuntimeException('Dateityp ist nicht erlaubt.');
  $width=null;$height=null;
  if(str_starts_with((string)$mime,'image/')){
   $image=@getimagesize($file['tmp_name']);
   if(!is_array($image)||empty($image[0])||empty($image[1]))throw new RuntimeException('Die Bilddatei ist technisch ungültig oder beschädigt.');
   $width=(int)$image[0];$height=(int)$image[1];
   if($minImageSide>0&&min($width,$height)<$minImageSide)throw new RuntimeException('Die Bildauflösung ist zu gering. Die kürzere Bildseite muss mindestens '.$minImageSide.' Pixel haben.');
  }
  $dir=$this->root.'/'.$bucket.'/'.date('Y/m');if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Speicherverzeichnis konnte nicht erstellt werden.');
  $path=$dir.'/'.bin2hex(random_bytes(24)).'.bin';if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Datei konnte nicht gespeichert werden.');
  return ['path'=>$path,'mime'=>$mime,'size'=>(int)filesize($path),'sha256'=>hash_file('sha256',$path),'original_name'=>(string)($file['name']??'datei'),'image_width'=>$width,'image_height'=>$height,'image_megapixels'=>($width&&$height)?round(($width*$height)/1000000,2):null];
 }
}