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

// If the requested asset exists directly in public/, serve it directly
if ($uri !== '/' && file_exists($publicPath.$uri) && ! is_dir($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
