<?php
// Standalone SMS Tester
require_once 'config/config.php';
require_once 'config/sms.php';

$result = '';
$log_content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $message = "Test message from Capstone at " . date('H:i:s');
    
    if ($phone) {
        echo "<h3>Attempting to send to: $phone</h3>";
        
        // 1. Direct Call
        $start = microtime(true);
        $status = sendSemaphoreSMS(9999, $phone, $message);
        $duration = microtime(true) - $start;
        
        $result = $status ? "<span style='color:green'>SUCCESS returned by function</span>" : "<span style='color:red'>FAILURE returned by function</span>";
        $result .= "<br>Time taken: " . number_format($duration, 2) . "s";
        
        // 2. Read Log
        $logFile = __DIR__ . '/logs/sms_debug.log';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $last_lines = array_slice($lines, -5);
            $log_content = implode("", $last_lines);
        } else {
            $log_content = "Log file not found at $logFile";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<body style="font-family:monospace; padding:20px;">
    <h2>SMS Diagnostic Tool</h2>
    <form method="POST">
        Phone: <input type="text" name="phone" value="09" placeholder="09xxxxxxxxx">
        <button type="submit">Send Test SMS</button>
    </form>
    
    <hr>
    <h3>Result:</h3>
    <div><?= $result ?></div>
    
    <h3>Recent Debug Log (Last 5 lines):</h3>
    <pre style="background:#eee; padding:10px;"><?= htmlspecialchars($log_content) ?></pre>
    
    <h3>Environment:</h3>
    <pre>
    API Key Set: <?= getenv('SEMAPHORE_API_KEY') ? 'YES' : 'NO' ?> 
    $_ENV Key Set: <?= isset($_ENV['SEMAPHORE_API_KEY']) ? 'YES' : 'NO' ?>
    Log Dir Writable: <?= is_writable(__DIR__ . '/logs') ? 'YES' : 'NO' ?>
    </pre>
</body>
</html>
