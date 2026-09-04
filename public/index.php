<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 1. PHP Version Pre-flight Check (Provide clear UI if server has PHP < 8.3)
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>PHP Version Error</title><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}';
    echo '.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;max-width:540px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.3)}';
    echo 'h1{color:#ef4444;font-size:22px;margin:0 0 16px}p{font-size:14px;line-height:1.6;color:#94a3b8}strong{color:#f8fafc}</style></head><body>';
    echo '<div class="card"><h1>PHP Version Incompatible</h1>';
    echo '<p>This application requires <strong>PHP 8.3</strong> or higher.</p>';
    echo '<p>Your server is currently running <strong>PHP '.htmlspecialchars(PHP_VERSION).'</strong>.</p>';
    echo '<p>Please switch your PHP version to <strong>8.3</strong> or <strong>8.4</strong> in your cPanel / hosting control panel (MultiPHP Manager or PHP Selector).</p>';
    echo '</div></body></html>';
    exit;
}

// 2. Pre-flight directory & environment self-healing
$baseDir = dirname(__DIR__);
$installedLock = $baseDir.'/storage/installed';

if (! file_exists($installedLock)) {
    $storageDirs = [
        $baseDir.'/storage/app/public',
        $baseDir.'/storage/framework/cache/data',
        $baseDir.'/storage/framework/sessions',
        $baseDir.'/storage/framework/views',
        $baseDir.'/storage/logs',
        $baseDir.'/bootstrap/cache',
    ];
    foreach ($storageDirs as $dir) {
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    $envFile = $baseDir.'/.env';
    if (! file_exists($envFile) && file_exists($baseDir.'/.env.example')) {
        @copy($baseDir.'/.env.example', $envFile);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request with diagnostic error catching
try {
    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->handleRequest(Request::capture());
} catch (Throwable $e) {
    // If not installed, output readable diagnostics so user never gets a blind 500 error
    if (! file_exists($installedLock)) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Installation Setup Error</title><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}';
        echo '.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;max-width:640px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.3)}';
        echo 'h1{color:#f59e0b;font-size:20px;margin:0 0 12px}p{font-size:14px;line-height:1.6;color:#94a3b8}pre{background:#0f172a;padding:16px;border-radius:8px;font-size:12px;color:#f87171;overflow-x:auto;border:1px solid #334155;white-space:pre-wrap}</style></head><body>';
        echo '<div class="card"><h1>Installation Pre-Flight Issue</h1>';
        echo '<p>The application encountered an issue during startup:</p>';
        echo '<pre>'.htmlspecialchars($e->getMessage())."\n\nFile: ".htmlspecialchars($e->getFile()).':'.$e->getLine().'</pre>';
        echo '<p>If this is a permission error, please ensure <strong>storage/</strong> and <strong>bootstrap/cache/</strong> are writeable (chmod 775 or 777).</p>';
        echo '</div></body></html>';
        exit;
    }
    throw $e;
}
