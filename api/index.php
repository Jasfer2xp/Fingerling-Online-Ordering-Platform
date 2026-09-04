<?php

/**
 * Vercel Serverless Entry Point Router
 */

$baseDir = dirname(__DIR__) . '/apps/web';
chdir($baseDir);
set_include_path(get_include_path() . PATH_SEPARATOR . $baseDir);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . ltrim($path, '/');

// Direct route for root
if ($path === '/' || $path === '') {
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $baseDir . '/index.php';
    require $baseDir . '/index.php';
    exit;
}

$target = $baseDir . $path;

// 1. Exact PHP file requested (e.g. /auth/login.php)
if (is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['PHP_SELF'] = $path;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    chdir(dirname($target));
    require $target;
    exit;
}

// 2. Directory with index.php (e.g. /customer/ or /admin/)
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.php')) {
    $indexPath = rtrim($target, '/') . '/index.php';
    $_SERVER['PHP_SELF'] = rtrim($path, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $indexPath;
    chdir(rtrim($target, '/'));
    require $indexPath;
    exit;
}

// 3. Clean URLs without .php extension (e.g. /auth/login)
if (is_file($target . '.php')) {
    $_SERVER['PHP_SELF'] = $path . '.php';
    $_SERVER['SCRIPT_FILENAME'] = $target . '.php';
    chdir(dirname($target));
    require $target . '.php';
    exit;
}

// 4. Fallback to apps/web/index.php
if (is_file($baseDir . '/index.php')) {
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $baseDir . '/index.php';
    require $baseDir . '/index.php';
    exit;
}

http_response_code(404);
echo "404 - Page Not Found";
