<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Web installer for shared hostings: shown until the application has a configuration (APP_KEY).
$stallfrontDataDir = $_SERVER['STALLFRONT_DATA_DIR'] ?? getenv('STALLFRONT_DATA_DIR') ?: dirname(__DIR__);
$stallfrontEnv = rtrim((string) $stallfrontDataDir, '/').'/.env';
if (! getenv('APP_KEY') && (! is_file($stallfrontEnv) || ! preg_match('/^APP_KEY=\S+/m', (string) file_get_contents($stallfrontEnv)))) {
    require __DIR__.'/../install/installer.php';
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
