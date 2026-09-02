<?php
require_once '../config/config.php';
session_start();

// Clear everything – force full logout
session_unset();
session_destroy();
session_write_close();
setcookie(session_name(), '', 0, '/');
session_regenerate_id(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Ended - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html { height: 100%; background: linear-gradient(135deg, #1e3a8a, #3b82f6); }
        .card { max-width: 480px; border: none; border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .icon-wrapper { width: 100px; height: 100px; background: #fef3c7; color: #f59e0b; border-radius: 50%; }
        .btn-home { background: #f59e0b; color: #111; font-weight: bold; }
        .btn-home:hover { background: #f97316; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="text-center p-5">
        <div class="card p-5">
            <div class="icon-wrapper d-flex align-items-center justify-content-center mx-auto mb-4">
                <i class="fas fa-user-slash fa-3x"></i>
            </div>
            <h2 class="fw-bold text-danger mb-3">Session Terminated</h2>
            <p class="lead text-muted mb-4">
                Your account was logged in from another device.<br>
                <strong>This session has been automatically ended for security.</strong>
            </p>
            <p class="text-muted small">
                If this wasn't you, please change your password immediately.
            </p>
            <div class="d-grid gap-3 mt-4">
                <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i> Log In Again
                </a>
                <a href="<?php echo base_url(); ?>" class="btn btn-home btn-lg">
                    <i class="fas fa-home me-2"></i> Back to Home
                </a>
            </div>
        </div>
        <p class="text-white mt-4 opacity-75 small">
            © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
        </p>
    </div>
</body>
</html>