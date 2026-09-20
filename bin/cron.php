<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/bootstrap.php';
$db=App\Core\Database::connection(dirname(__DIR__));
$result=(new App\Services\SchedulerService($db))->run();
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
