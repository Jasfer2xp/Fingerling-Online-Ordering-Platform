<?php
/**
 * Script to add session checking to all protected pages
 * 
 * This script adds "require_once '../includes/check_session.php';" 
 * after "if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';" in all protected pages.
 */

// Only allow execution if explicitly requested
if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Add Session Check to Protected Pages</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card'>
                        <div class='card-header'>
                            <h2>Add Session Check to Protected Pages</h2>
                        </div>
                        <div class='card-body'>
                            <p>This script will add session checking to all protected pages in:</p>
                            <ul>
                                <li>Customer directory</li>
                                <li>Supplier directory</li>
                                <li>Admin directory</li>
                            </ul>
                            <p>It will add the line:</p>
                            <code>require_once '../includes/check_session.php';</code>
                            <p>after:</p>
                            <code>require_once '../config/config.php';</code>
                            <div class='alert alert-warning mt-3'>
                                <strong>Warning:</strong> This will modify many files. 
                                Make sure you have backed up your project before proceeding.
                            </div>
                            <a href='?execute=1' class='btn btn-primary' 
                               onclick='return confirm(\"Are you sure you want to modify all protected pages?\")'>
                               Execute Session Check Addition
                            </a>
                            <a href='index.php' class='btn btn-secondary'>Cancel and Return to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// Define directories to scan
$directories = [
    'customer',
    'supplier',
    'admin'
];

// Files to exclude (index files, etc.)
$excludeFiles = [
    'index.php',
    'test_add_to_cart.php',
    'test_stock_validation.php'
];

// Log of processed files
$processedFiles = [];
$skippedFiles = [];
$failedFiles = [];

// Function to process a file
function processFile($filePath, $relativePath) {
    global $processedFiles, $skippedFiles, $failedFiles, $excludeFiles;
    
    // Get filename
    $fileName = basename($filePath);
    
    // Skip excluded files
    if (in_array($fileName, $excludeFiles)) {
        $skippedFiles[] = $relativePath;
        return;
    }
    
    // Only process PHP files
    if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
        $skippedFiles[] = $relativePath;
        return;
    }
    
    // Read file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        $failedFiles[] = $relativePath;
        return;
    }
    
    // Check if file already has the session check
    if (strpos($content, "require_once '../includes/check_session.php';") !== false) {
        $skippedFiles[] = $relativePath . " (already has session check)";
        return;
    }
    
    // Check if file has the config require
    if (strpos($content, "require_once '../config/config.php';") === false) {
        $skippedFiles[] = $relativePath . " (no config include found)";
        return;
    }
    
    // Add the session check after the config require
    $newContent = str_replace(
        "require_once '../config/config.php';",
        "require_once '../config/config.php';\nrequire_once '../includes/check_session.php';",
        $content
    );
    
    // Write the updated content back to the file
    if (file_put_contents($filePath, $newContent) !== false) {
        $processedFiles[] = $relativePath;
    } else {
        $failedFiles[] = $relativePath;
    }
}

// Process files in each directory
foreach ($directories as $directory) {
    $dirPath = __DIR__ . '/' . $directory;
    
    if (is_dir($dirPath)) {
        if ($handle = opendir($dirPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $filePath = $dirPath . '/' . $entry;
                    $relativePath = $directory . '/' . $entry;
                    
                    if (is_file($filePath)) {
                        processFile($filePath, $relativePath);
                    }
                }
            }
            closedir($handle);
        }
    }
}

// Display results
echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Check Addition Results</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card'>
                    <div class='card-header'>
                        <h2>Session Check Addition Results</h2>
                    </div>
                    <div class='card-body'>";

if (!empty($processedFiles)) {
    echo "<div class='alert alert-success'>
            <h4>Processed Files (" . count($processedFiles) . ")</h4>
            <ul class='list-group'>";
    foreach ($processedFiles as $file) {
        echo "<li class='list-group-item'>" . htmlspecialchars($file) . "</li>";
    }
    echo "</ul>
          </div>";
}

if (!empty($skippedFiles)) {
    echo "<div class='alert alert-info'>
            <h4>Skipped Files (" . count($skippedFiles) . ")</h4>
            <ul class='list-group'>";
    foreach ($skippedFiles as $file) {
        echo "<li class='list-group-item'>" . htmlspecialchars($file) . "</li>";
    }
    echo "</ul>
          </div>";
}

if (!empty($failedFiles)) {
    echo "<div class='alert alert-danger'>
            <h4>Failed Files (" . count($failedFiles) . ")</h4>
            <ul class='list-group'>";
    foreach ($failedFiles as $file) {
        echo "<li class='list-group-item'>" . htmlspecialchars($file) . "</li>";
    }
    echo "</ul>
          </div>";
}

// Delete this script file itself
echo "<div class='alert alert-info mt-3'>
        <p>Process completed.</p>";

echo "<a href='index.php' class='btn btn-primary'>Return to Homepage</a>";
echo "      </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
?>