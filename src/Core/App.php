<?php
namespace App\Core;

use App\Http\Controllers\InstallerController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;

final class App{
 public function __construct(private string $root){}
 public function run(Request $request):void{
  try{
   $router=new Router();
   if(!Database::configured($this->root)){
    $installer=new InstallerController($this->root);
    $router->get('/install',[$installer,'show']);
    $router->post('/install',[$installer,'install']);
    if($request->path!=='/install') Response::redirect('/install');
    $router->dispatch($request);return;
   }
   $db=Database::connection($this->root);
   $auth=new Auth($db);
   $csrf=function(Request $r):void{if($r->method==='POST'&&!Csrf::verify((string)$r->input('_csrf')))Response::abort(419,'Sicherheitsprüfung fehlgeschlagen.');};
   $sellerOnly=function()use($auth):void{if(!$auth->seller())Response::redirect('/login');};
   $verifiedSeller=function()use($auth):void{$s=$auth->seller();if(!$s)Response::redirect('/login');if(empty($s['email_verified_at']))Response::redirect('/konto/email-bestaetigen');};
   $adminOnly=function()use($auth):void{if(!$auth->admin())Response::redirect('/admin/login');};

   $public=new PublicController($this->root,$db,$auth);
   $sellerAuth=new AuthController($this->root,$db,$auth);
   $seller=new SellerController($this->root,$db,$auth);
   $adminAuth=new AdminAuthController($this->root,$db,$auth);
   $admin=new AdminController($this->root,$db,$auth);

   $router->get('/',[$public,'home']);
   $router->get('/angebote',[$public,'offers']);
   $router->get('/angebote/{id}',[$public,'offer']);
   $router->get('/so-funktioniert-es',[$public,'howItWorks']);
   $router->get('/faq',[$public,'faq']);
   $router->get('/kontakt',[$public,'contact']);
   $router->get('/impressum',[$public,'imprint']);
   $router->get('/datenschutz',[$public,'privacy']);
   $router->get('/bedingungen',[$public,'terms']);

   $router->get('/registrieren',[$sellerAuth,'registerForm']);
   $router->post('/registrieren',[$sellerAuth,'register'],[$csrf]);
   $router->get('/login',[$sellerAuth,'loginForm']);
   $router->post('/login',[$sellerAuth,'login'],[$csrf]);
   $router->post('/logout',[$sellerAuth,'logout'],[$csrf,$sellerOnly]);
   $router->get('/email-verifizieren/{token}',[$sellerAuth,'verifyEmail']);
   $router->get('/konto/email-bestaetigen',[$sellerAuth,'verificationNotice'],[$sellerOnly]);
   $router->post('/konto/email-bestaetigen',[$sellerAuth,'resendVerification'],[$csrf,$sellerOnly]);
   $router->get('/passwort-vergessen',[$sellerAuth,'forgotForm']);
   $router->post('/passwort-vergessen',[$sellerAuth,'forgot'],[$csrf]);
   $router->get('/passwort-zuruecksetzen/{token}',[$sellerAuth,'resetForm']);
   $router->post('/passwort-zuruecksetzen/{token}',[$sellerAuth,'reset'],[$csrf]);

   $router->get('/konto',[$seller,'dashboard'],[$sellerOnly]);
   $router->get('/konto/auftraege',[$seller,'orders'],[$verifiedSeller]);
   $router->get('/konto/wallet',[$seller,'wallet'],[$sellerOnly]);
   $router->get('/konto/benachrichtigungen',[$seller,'notifications'],[$sellerOnly]);

   $router->get('/admin/login',[$adminAuth,'loginForm']);
   $router->post('/admin/login',[$adminAuth,'login'],[$csrf]);
   $router->post('/admin/logout',[$adminAuth,'logout'],[$csrf,$adminOnly]);
   $router->get('/admin',[$admin,'dashboard'],[$adminOnly]);
   $router->get('/admin/kategorien',[$admin,'categories'],[$adminOnly]);
   $router->post('/admin/kategorien',[$admin,'createCategory'],[$csrf,$adminOnly]);
   $router->get('/admin/angebote',[$admin,'offers'],[$adminOnly]);

   $router->dispatch($request);
  }catch(\Throwable $e){
   Logger::error($this->root,$e->getMessage(),['file'=>$e->getFile(),'line'=>$e->getLine()]);
   if(defined('APP_DEBUG')&&APP_DEBUG)throw $e;
   Response::abort(500,'Ein interner Fehler ist aufgetreten.');
  }
 }
}