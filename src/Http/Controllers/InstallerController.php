<?php
namespace App\Http\Controllers;
use App\Core\Csrf;use App\Core\MigrationRunner;use App\Core\Request;use App\Core\Response;use App\Core\View;use PDO;
final class InstallerController{
 public function __construct(private string $root){}
 public function show():void{if(is_file($this->root.'/storage/installed.lock'))Response::abort(403,'Installation ist gesperrt.');$checks=['PHP >= 8.1'=>version_compare(PHP_VERSION,'8.1.0','>='),'PDO MySQL'=>extension_loaded('pdo_mysql'),'Fileinfo'=>extension_loaded('fileinfo'),'mbstring'=>extension_loaded('mbstring'),'config/ beschreibbar'=>is_writable($this->root.'/config'),'storage/ beschreibbar'=>is_dir($this->root.'/storage')?is_writable($this->root.'/storage'):is_writable($this->root)];View::render($this->root,'install',['checks'=>$checks,'error'=>null]);}
 public function install(Request $r):void{
  if(is_file($this->root.'/storage/installed.lock'))Response::abort(403,'Installation ist gesperrt.');
  if(!Csrf::verify((string)$r->input('_csrf')))Response::abort(419,'Sicherheitsprüfung fehlgeschlagen.');
  $host=trim((string)$r->input('db_host','localhost'));$port=(int)$r->input('db_port',3306);$name=trim((string)$r->input('db_name'));$user=trim((string)$r->input('db_user'));$pass=(string)$r->input('db_password');$adminEmail=trim(mb_strtolower((string)$r->input('admin_email')));$adminPassword=(string)$r->input('admin_password');$baseUrl=rtrim(trim((string)$r->input('base_url')),'/');$contact=trim(mb_strtolower((string)$r->input('contact_email')));$operator=trim((string)$r->input('operator_name'));$street=trim((string)$r->input('operator_street'));$postal=trim((string)$r->input('operator_postal'));$city=trim((string)$r->input('operator_city'));
  if(!$name||!$user||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||strlen($adminPassword)<12||!filter_var($baseUrl,FILTER_VALIDATE_URL)||!filter_var($contact,FILTER_VALIDATE_EMAIL)||!$operator||!$street||!$postal||!$city){View::render($this->root,'install',['checks'=>[],'error'=>'Bitte alle Pflichtangaben vollständig und gültig eingeben. Das Admin-Passwort muss mindestens 12 Zeichen lang sein.']);return;}
  $dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
  try{
   $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
   foreach(['storage','storage/private','storage/logs'] as $d){$p=$this->root.'/'.$d;if(!is_dir($p)&&!mkdir($p,0770,true)&&!is_dir($p))throw new \RuntimeException('Verzeichnis '.$d.' konnte nicht angelegt werden.');}
   $dbConfig="<?php\nreturn ".var_export(['dsn'=>$dsn,'user'=>$user,'password'=>$pass],true).";\n";if(file_put_contents($this->root.'/config/database.php',$dbConfig,LOCK_EX)===false)throw new \RuntimeException('Datenbankkonfiguration konnte nicht gespeichert werden.');
   $runtime="<?php\nreturn ".var_export(['base_url'=>$baseUrl,'mail_from'=>$contact,'mail_from_name'=>'FetischShop'],true).";\n";if(file_put_contents($this->root.'/config/runtime.php',$runtime,LOCK_EX)===false)throw new \RuntimeException('Laufzeitkonfiguration konnte nicht gespeichert werden.');
   (new MigrationRunner($pdo,$this->root))->run();if((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()>0)throw new \RuntimeException('Es existiert bereits ein Admin-Konto.');$pdo->prepare('INSERT INTO admins(email,password_hash,created_at,updated_at) VALUES(?,?,NOW(),NOW())')->execute([$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT)]);
   $settings=['contact_email'=>$contact,'operator_name'=>$operator,'operator_street'=>$street,'operator_postal'=>$postal,'operator_city'=>$city,'operator_phone'=>trim((string)$r->input('operator_phone')),'public_url'=>$baseUrl];$st=$pdo->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');foreach($settings as $k=>$v)$st->execute([$k,$v]);
   $lock=$this->root.'/storage/installed.lock';if(file_put_contents($lock,date('c').PHP_EOL,LOCK_EX)===false)throw new RuntimeException('Installationssperre konnte nicht geschrieben werden.');@chmod($lock,0640);
   Response::redirect('/admin/login?installed=1');
  }catch(\Throwable $e){@unlink($this->root.'/config/database.php');@unlink($this->root.'/config/runtime.php');View::render($this->root,'install',['checks'=>[],'error'=>$e->getMessage()]);}
 }
}