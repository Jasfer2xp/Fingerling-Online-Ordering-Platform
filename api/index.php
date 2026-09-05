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

// 0. Auto-fix duplicate domain names in path (e.g. /fingerlings.vercel.app/auth/...)
if (preg_match('#^/([a-zA-Z0-9.-]+\.vercel\.app|fingerling\.shop|localhost)(/.*)$#i', $path, $matches)) {
    $cleanPath = $matches[2];
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $cleanPath . $queryString, true, 301);
    exit;
}

// 1. Static Asset Handler (GIFs, Images, CSS, JS, Fonts, Icons)
$staticMimeTypes = [
    'gif'   => 'image/gif',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'webp'  => 'image/webp',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
    'json'  => 'application/json',
    'pdf'   => 'application/pdf',
];

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (isset($staticMimeTypes[$ext])) {
    $assetFile = $baseDir . $path;
    if (is_file($assetFile)) {
        header('Content-Type: ' . $staticMimeTypes[$ext]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($assetFile));
        readfile($assetFile);
        exit;
    }
}

// 2. Clean Auth Route Aliases (e.g. /login -> auth/login.php)
$cleanAliases = [
    '/login'                  => '/auth/login.php',
    '/register'               => '/auth/register.php',
    '/logout'                 => '/auth/logout.php',
    '/verify-2fa'             => '/auth/verify_2fa.php',
    '/forgot-password'        => '/auth/forgot-password.php',
    '/reset-password'         => '/auth/reset-password.php',
    '/google-callback'        => '/auth/google-callback.php',
    '/google-role-selection'  => '/auth/google-role-selection.php',
    '/report-suspension'      => '/auth/report_suspension.php',
];

if (isset($cleanAliases[$path])) {
    $target = $baseDir . $cleanAliases[$path];
    if (is_file($target)) {
        $_SERVER['PHP_SELF'] = $cleanAliases[$path];
        $_SERVER['SCRIPT_FILENAME'] = $target;
        chdir(dirname($target));
        require $target;
        exit;
    }
}

// 3. Direct route for root
if ($path === '/' || $path === '') {
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $baseDir . '/index.php';
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

$target = $baseDir . $path;

// 4. Exact PHP file requested (e.g. /auth/login.php)
if (is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['PHP_SELF'] = $path;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    chdir(dirname($target));
    require $target;
    exit;
}

// 5. Directory with index.php (e.g. /customer/ or /admin/)
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.php')) {
    $indexPath = rtrim($target, '/') . '/index.php';
    $_SERVER['PHP_SELF'] = rtrim($path, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $indexPath;
    chdir(rtrim($target, '/'));
    require $indexPath;
    exit;
}

// 6. Clean URLs without .php extension (e.g. /customer/dashboard -> /customer/dashboard.php)
if (is_file($target . '.php')) {
    $_SERVER['PHP_SELF'] = $path . '.php';
    $_SERVER['SCRIPT_FILENAME'] = $target . '.php';
    chdir(dirname($target));
    require $target . '.php';
    exit;
}

// 7. Check if path exists inside /auth/ without prefix (e.g. /login.php -> /auth/login.php)
if (is_file($baseDir . '/auth' . $path)) {
    $authTarget = $baseDir . '/auth' . $path;
    $_SERVER['PHP_SELF'] = '/auth' . $path;
    $_SERVER['SCRIPT_FILENAME'] = $authTarget;
    chdir(dirname($authTarget));
    require $authTarget;
    exit;
}

// 8. Fallback to apps/web/index.php
if (is_file($baseDir . '/index.php')) {
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $baseDir . '/index.php';
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

http_response_code(404);
echo "404 - Page Not Found";
