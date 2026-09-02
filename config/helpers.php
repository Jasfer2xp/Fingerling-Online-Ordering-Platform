<?php

/**
 * Helper functions for the application
 */

if (!function_exists('load_env')) {
    /**
     * Load environment variables from .env file
     * 
     * @param string $path Path to the .env file
     * @return void
     */
    function load_env($path = null) {
        if (!$path) {
            $path = __DIR__ . '/../.env';
        }
        
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove surrounding quotes if present
                $value = trim($value, '"\'');
                
                // Set both $_ENV and via putenv()
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     * 
     * @param string $key The environment variable key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function env($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Handle boolean values
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }
        
        return $value;
    }
}
?>