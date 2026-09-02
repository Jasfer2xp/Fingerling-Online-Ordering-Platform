<?php

// includes/check_session.php
// Include this file on every protected page (after config.php)

if (!isset($_SESSION)) {
    session_start();
}

// Not logged in at all → go to login
if (!isset($_SESSION['user_id'])) {
    redirect(base_url('auth/login.php'));
    exit;
}

// === SINGLE SESSION VALIDATION START ===
$user_id           = $_SESSION['user_id'];
$stored_session_id   = $_SESSION['session_id'] ?? null;

if ($stored_session_id) {
    $stmt = $pdo->prepare("SELECT current_session_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $db_session_id = $stmt->fetchColumn();

    // Session has been taken over by another login
    if (!$db_session_id || $db_session_id !== $stored_session_id) {
        // Destroy everything cleanly
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', 0, '/');
        session_regenerate_id(true);

        // Show beautiful takeover notice instead of login form
        header("Location: " . base_url('auth/session-takeover.php'));
        exit;
    }

    // Update last activity (helps with inactivity timeout too)
    $pdo->prepare("UPDATE users SET session_last_activity = NOW() WHERE id = ?")
        ->execute([$user_id]);
}
// === SINGLE SESSION VALIDATION END ===    

// Security: Regenerate session ID every 30 minutes
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>