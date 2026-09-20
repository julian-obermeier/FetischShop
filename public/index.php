<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$app=new App\Core\App(dirname(__DIR__));
$app->run(App\Core\Request::capture());
