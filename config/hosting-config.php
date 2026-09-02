<?php

/**
 * Hosting-Specific Database Configuration
 * Update these settings for your hosting providers
 */
require_once __DIR__ . '/helpers.php';

// ===========================================
// INFINITYFREE HOSTING SETTINGS
// ===========================================
// Get these from your InfinityFree control panel > MySQL Databases
define('INFINITYFREE_HOST', env('INFINITYFREE_HOST', 'sql200.infinityfree.com')); // Your MySQL hostname
define('INFINITYFREE_DB_NAME', env('INFINITYFREE_DB_NAME', 'if0_39794261_fingerling_marketplace')); // Your database name
define('INFINITYFREE_USERNAME', env('INFINITYFREE_USERNAME', 'if0_39794261')); // Your database username
define('INFINITYFREE_PASSWORD', env('INFINITYFREE_PASSWORD', 'YOUR_INFINITYFREE_PASSWORD_HERE')); // Your database password

// ===========================================
// HOSTINGER HOSTING SETTINGS
// ===========================================
// Get these from your Hostinger control panel > MySQL Databases
define('HOSTINGER_HOST', env('HOSTINGER_HOST', 'localhost')); // Usually localhost for Hostinger
define('HOSTINGER_DB_NAME', env('HOSTINGER_DB_NAME', 'u123456789_fingerling')); // Your database name
define('HOSTINGER_USERNAME', env('HOSTINGER_USERNAME', 'u123456789_username')); // Your database username
define('HOSTINGER_PASSWORD', env('HOSTINGER_PASSWORD', 'YOUR_HOSTINGER_PASSWORD_HERE')); // Your database password

// ===========================================
// LOCALHOST/XAMPP SETTINGS
// ===========================================
define('LOCALHOST_HOST', env('LOCALHOST_HOST', 'localhost'));
define('LOCALHOST_DB_NAME', env('LOCALHOST_DB_NAME', 'fingerling_marketplace'));
define('LOCALHOST_USERNAME', env('LOCALHOST_USERNAME', 'root'));
define('LOCALHOST_PASSWORD', env('LOCALHOST_PASSWORD', ''));

// ===========================================
// ADDITIONAL HOSTING PROVIDERS
// ===========================================
// Add more hosting providers as needed

// 000WebHost
define('WEBHOST_HOST', getenv('WEBHOST_HOST') ?: 'databases.000webhost.com');
define('WEBHOST_DB_NAME', getenv('WEBHOST_DB_NAME') ?: 'id12345678_fingerling');
define('WEBHOST_USERNAME', getenv('WEBHOST_USERNAME') ?: 'id12345678_username');
define('WEBHOST_PASSWORD', getenv('WEBHOST_PASSWORD') ?: 'YOUR_WEBHOST_PASSWORD');

// cPanel Hosting (Generic)
define('CPANEL_HOST', env('CPANEL_HOST', 'localhost'));
define('CPANEL_DB_NAME', env('CPANEL_DB_NAME', 'username_fingerling'));
define('CPANEL_USERNAME', env('CPANEL_USERNAME', 'username_dbuser'));
define('CPANEL_PASSWORD', env('CPANEL_PASSWORD', 'YOUR_CPANEL_PASSWORD'));

?>

<!-- 
INSTRUCTIONS FOR UPDATING YOUR DATABASE SETTINGS:

1. FOR INFINITYFREE:
   - Login to InfinityFree control panel
   - Go to "MySQL Databases"
   - Copy the hostname (usually sql200.infinityfree.com or similar)
   - Copy your database name (starts with if0_)
   - Copy your username (same as database name)
   - Update the INFINITYFREE_* constants above

2. FOR HOSTINGER:
   - Login to Hostinger control panel
   - Go to "MySQL Databases"
   - Copy your database details
   - Update the HOSTINGER_* constants above

3. FOR LOCALHOST/XAMPP:
   - Usually no changes needed
   - Default settings should work

4. AFTER UPDATING:
   - Save this file
   - Upload to your hosting server
   - Test your website

SECURITY NOTE:
- Never share this file publicly
- Add this file to .gitignore if using version control
- Use environment variables in production for better security
-->
