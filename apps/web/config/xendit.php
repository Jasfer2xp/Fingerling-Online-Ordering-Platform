<?php

/**
 * Xendit Configuration
 * Fingerling Online Ordering Platform System
 */

// Xendit credentials must only be supplied by the environment/.env file.
$xendit_api_key = env('XENDIT_API_KEY', '');

// Only define constants if they don't already exist (prevents redefinition warnings)
if (!defined('XENDIT_API_KEY')) {
    define('XENDIT_API_KEY', $xendit_api_key);
}
if (!defined('XENDIT_WEBHOOK_TOKEN')) {
    define('XENDIT_WEBHOOK_TOKEN', env('XENDIT_WEBHOOK_TOKEN', ''));
}
if (!defined('XENDIT_BASE_URL')) {
    define('XENDIT_BASE_URL', 'https://api.xendit.co');
}
if (!defined('XENDIT_API_VERSION')) {
    define('XENDIT_API_VERSION', '2.0');
}
if (!defined('XENDIT_TRANSACTION_FEE_PERCENTAGE')) {
    define('XENDIT_TRANSACTION_FEE_PERCENTAGE', 2.576); // 2.576%
}
