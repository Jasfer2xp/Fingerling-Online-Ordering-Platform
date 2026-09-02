<?php
/**
 * Cleanup Unverified Accounts
 * 
 * This script deletes customer and supplier accounts that have not been verified
 * via email OTP within 30 minutes of registration.
 * 
 * Run this script via cron every 5-10 minutes:
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/capstone/cron/cleanup-unverified-accounts.php
 */

require_once __DIR__ . '/../config/config.php';

try {
    $database->beginTransaction();
    
    // Find unverified accounts older than 30 minutes
    // Check both verification created_at and user created_at as fallback
    $sql = "SELECT u.id, u.user_type, u.email, u.created_at as user_created_at, v.created_at as verification_created_at
            FROM users u
            INNER JOIN user_email_verifications v ON u.id = v.user_id
            WHERE v.verified_at IS NULL
            AND (
                v.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                OR u.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            )
            AND u.status != 'deleted'";
    
    $unverified_accounts = $database->fetchAll($sql);
    
    $deleted_count = 0;
    $errors = [];
    
    foreach ($unverified_accounts as $account) {
        try {
            $user_id = $account['id'];
            $user_type = $account['user_type'];
            $email = $account['email'];
            
            // Delete customer or supplier record first (if exists)
            if ($user_type === 'customer') {
                $database->query("DELETE FROM customers WHERE user_id = ?", [$user_id]);
            } elseif ($user_type === 'supplier') {
                $database->query("DELETE FROM suppliers WHERE user_id = ?", [$user_id]);
            }
            
            // Delete email verification record
            $database->query("DELETE FROM user_email_verifications WHERE user_id = ?", [$user_id]);
            
            // Delete user record (CASCADE will handle related records like sessions)
            $database->query("DELETE FROM users WHERE id = ?", [$user_id]);
            
            $deleted_count++;
            error_log("Cleanup: Deleted unverified {$user_type} account (ID: {$user_id}, Email: {$email})");
            
        } catch (Exception $e) {
            $errors[] = "Failed to delete user ID {$user_id}: " . $e->getMessage();
            error_log("Cleanup error for user ID {$user_id}: " . $e->getMessage());
        }
    }
    
    $database->commit();
    
    if ($deleted_count > 0) {
        error_log("Cleanup completed: Deleted {$deleted_count} unverified account(s)");
    }
    
    if (!empty($errors)) {
        error_log("Cleanup completed with errors: " . implode('; ', $errors));
    }
    
    // Output for cron logging
    echo date('Y-m-d H:i:s') . " - Cleanup completed: {$deleted_count} unverified account(s) deleted\n";
    
} catch (Exception $e) {
    $database->rollback();
    error_log("Cleanup script fatal error: " . $e->getMessage());
    echo date('Y-m-d H:i:s') . " - Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}

