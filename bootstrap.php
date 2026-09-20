<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
spl_autoload_register(static function (string $class): void {
    $prefix='App\\';
    if(!str_starts_with($class,$prefix)) return;
    $path=__DIR__.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path)) require $path;
});
if(is_file(__DIR__.'/config/app.php')){
    $config=require __DIR__.'/config/app.php';
    if(isset($config['timezone'])) date_default_timezone_set((string)$config['timezone']);
}
App\Core\Session::start();
