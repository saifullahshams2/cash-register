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
if ($uri !== '/' && file_exists($publicPath.$uri) && ! is_dir($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
