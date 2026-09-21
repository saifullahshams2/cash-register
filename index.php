<?php

/**
 * Cash Register POS - Shared Hosting & cPanel Root Proxy
 *
 * This file allows the application to run seamlessly when the server DocumentRoot
 * points to the repository root directory instead of /public.
 */
$publicPath = __DIR__.'/public';

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';

// Reject any request containing NUL bytes in raw or decoded form
if (str_contains($rawUri, "\0") || str_contains(urldecode($rawUri), "\0")) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$parsedPath = parse_url($rawUri, PHP_URL_PATH);
$uri = urldecode($parsedPath !== false && $parsedPath !== null ? $parsedPath : $rawUri);

// Reject any decoded path containing parent-directory segments or traversal
if (preg_match('~(^|[\\/\\\\])\\.\\.([\\/\\\\]|$)~', $uri) || str_contains($uri, '..')) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// Explicitly forbid requests attempting to access sensitive files/directories at the project root
if (preg_match('/(^|\/)(\.env|\.git|composer\.(json|lock)|artisan|bootstrap\/|config\/|app\/|database\/|storage\/logs\/|tests\/|routes\/|.*\.zip)/i', $uri)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// If the requested asset exists directly in public/, serve it directly
$targetAsset = $publicPath.$uri;
$realPublic = realpath($publicPath);
$realStorage = realpath(__DIR__.'/storage/app/public');
$realTarget = realpath($targetAsset);

$isContained = $realTarget && (
    ($realPublic && str_starts_with($realTarget, $realPublic)) ||
    ($realStorage && str_starts_with($realTarget, $realStorage))
);

if ($uri !== '/' && $isContained && ! is_dir($realTarget) && ! str_ends_with($realTarget, '.php')) {
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
