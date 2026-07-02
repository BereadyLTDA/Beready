<?php
// CORS headers - valores hardcoded (sem usar env() antes do autoload)
$corsOrigin = getenv('APP_BASE_URL');

header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With, Accept');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT')) {
    require_once dirname(__DIR__) . DS . 'config' . DS . 'paths.php';
}
require_once ROOT . DS . 'vendor' . DS . 'autoload.php';

$app = new App\Application(CONFIG);
$server = new Cake\Http\Server($app);
$server->emit($server->run());