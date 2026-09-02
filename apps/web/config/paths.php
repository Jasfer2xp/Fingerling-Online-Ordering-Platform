<?php

/**
 * Canonical paths for the monorepo layout.
 *
 * The web application lives in apps/web while operational files live outside
 * the public application tree.
 */
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('RUNTIME_PATH')) {
    define('RUNTIME_PATH', PROJECT_ROOT . '/runtime');
}
