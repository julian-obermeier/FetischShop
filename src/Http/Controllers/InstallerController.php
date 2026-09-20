<?php
namespace App\Http\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use PDO;

final class InstallerController{
 public function __construct(private string $root){}
 public function show():void{
  $checks=[
   'PHP >= 8.1'=>version_compare(PHP_VERSION,'8.1.0','>='),
   'PDO MySQL'=>extension_loaded('pdo_mysql'),
   'Fileinfo'=>extension_loaded('fileinfo'),
   'mbstring'=>extension_loaded('mbstring'),
   'config/ beschreibbar'=>is_writable($this->root.'/config'),
   'storage/ beschreibbar'=>is_dir($this->root.'/storage')?is_writable($this->root.'/storage'):is_writable($this->root),
  ];
  View::render($this->root,'install',['checks'=>$checks,'error'=>null]);
 }
 public function install(Request $r):void{
  if(!Csrf::verify((string)$r->input('_csrf')))Response::abort(419,'Sicherheitsprüfung fehlgeschlagen.');
  $host=trim((string)$r->input('db_host','localhost'));$port=(int)$r->input('db_port',3306);$name=trim((string)$r->input('db_name'));$user=trim((string)$r->input('db_user'));$pass=(string)$r->input('db_password');
  $adminEmail=trim(mb_strtolower((string)$r->input('admin_email')));$adminPassword=(string)$r->input('admin_password');
  if(!$name||!$user||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||strlen($adminPassword)<12){View::render($this->root,'install',['checks'=>[],'error'=>'Bitte alle Daten vollständig eingeben. Das Admin-Passwort muss mindestens 12 Zeichen lang sein.']);return;}
  $dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
  try{
   $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
   foreach(['storage','storage/private','storage/logs'] as $d){$p=$this->root.'/'.$d;if(!is_dir($p)&&!mkdir($p,0770,true)&&!is_dir($p))throw new \RuntimeException('Verzeichnis '.$d.' konnte nicht angelegt werden.');}
   $config="<?php\nreturn ".var_export(['dsn'=>$dsn,'user'=>$user,'password'=>$pass],true).";\n";
   if(file_put_contents($this->root.'/config/database.php',$config,LOCK_EX)===false)throw new \RuntimeException('Datenbankkonfiguration konnte nicht gespeichert werden.');
   (new MigrationRunner($pdo,$this->root))->run();
   $count=(int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
   if($count>0)throw new \RuntimeException('Es existiert bereits ein Admin-Konto.');
   $q=$pdo->prepare('INSERT INTO admins(email,password_hash,created_at,updated_at) VALUES(?,?,NOW(),NOW())');
   $q->execute([$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT)]);
   Response::redirect('/admin/login?installed=1');
  }catch(\Throwable $e){
   @unlink($this->root.'/config/database.php');
   View::render($this->root,'install',['checks'=>[],'error'=>$e->getMessage()]);
  }
 }
}