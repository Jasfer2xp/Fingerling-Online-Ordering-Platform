<?php

/**
 * Vercel Serverless Entry Point Router
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . ltrim($path, '/');

$baseDir = dirname(__DIR__) . '/apps/web';

// Direct route for root
if ($path === '/' || $path === '') {
    require $baseDir . '/index.php';
    exit;
}

$target = $baseDir . $path;

// 1. Exact PHP file requested (e.g. /auth/login.php)
if (is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    require $target;
    exit;
}

// 2. Directory with index.php (e.g. /customer/ or /admin/)
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.php')) {
    require rtrim($target, '/') . '/index.php';
    exit;
}

// 3. Clean URLs without .php extension (e.g. /auth/login)
if (is_file($target . '.php')) {
    require $target . '.php';
    exit;
}

// 4. Fallback to apps/web/index.php
if (is_file($baseDir . '/index.php')) {
    require $baseDir . '/index.php';
    exit;
}

http_response_code(404);
echo "404 - Page Not Found";
