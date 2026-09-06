<?php

/**
 * Cash Register POS - Shared Hosting & cPanel Root Proxy
 *
 * This file allows the application to run seamlessly when the server DocumentRoot
 * points to the repository root directory instead of /public.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? ''
);

// Explicitly forbid requests attempting to access sensitive files/directories at the project root
if (preg_match('/(^|\/)(\.env|\.git|composer\.(json|lock)|artisan|database\/|storage\/logs\/|.*\.zip)/i', $uri)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// If the requested asset exists directly in public/, serve it directly
$targetAsset = $publicPath.$uri;
if ($uri !== '/' && file_exists($targetAsset) && ! is_dir($targetAsset)) {
    if (php_sapi_name() === 'cli-server') {
        return false;
    }

    $extension = strtolower(pathinfo($targetAsset, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'pdf' => 'application/pdf',
        'map' => 'application/json',
    ];

    $contentType = $mimes[$extension] ?? (function_exists('mime_content_type') ? (mime_content_type($targetAsset) ?: 'application/octet-stream') : 'application/octet-stream');
    header('Content-Type: '.$contentType);
    header('Content-Length: '.(string) filesize($targetAsset));
    header('Cache-Control: public, max-age=31536000');
    readfile($targetAsset);
    exit;
}

require_once $publicPath.'/index.php';
