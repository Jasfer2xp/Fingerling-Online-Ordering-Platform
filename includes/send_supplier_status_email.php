<?php
/**
 * Helper to send supplier status lifecycle notifications
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!function_exists('send_supplier_status_email')) {
    /**
     * Send a supplier status notification email.
     *
     * @param int    $supplier_id
     * @param string $status          approved|rejected|suspended|reactivated
     * @param string $admin_comments  optional notes to include
     * @return array ['success'=>bool,'error'=>string]
     */
    function send_supplier_status_email($supplier_id, $status, $admin_comments = '')
    {
        global $database;

        if (!$supplier_id) {
            return ['success' => false, 'error' => 'invalid_supplier_id'];
        }

        $db = $database ?? new Database();

        try {
            $supplier = $db->fetch(
                "SELECT s.business_name, s.owner_name, u.email
                 FROM suppliers s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.id = ?",
                [$supplier_id]
            );
        } catch (Exception $e) {
            error_log('send_supplier_status_email: supplier lookup failed - ' . $e->getMessage());
            return ['success' => false, 'error' => 'supplier_lookup_failed'];
        }

        if (!$supplier || empty($supplier['email'])) {
            return ['success' => false, 'error' => 'supplier_not_found'];
        }

        $owner_name = $supplier['owner_name'] ?: 'Supplier';
        $business = $supplier['business_name'] ?: 'your business';
        $email = $supplier['email'];

        $support_email = env('SUPPORT_EMAIL', defined('FROM_EMAIL') ? FROM_EMAIL : 'support@fingerling.shop');
        $login_url = base_url('auth/login.php');
        $status_key = strtolower(trim($status));
        $notes_block = $admin_comments ? "<p><strong>Notes from admin:</strong><br>" . nl2br(htmlspecialchars($admin_comments)) . "</p>" : '';

        $subject = '';
        $body = '';

        switch ($status_key) {
            case 'approved':
                $subject = 'Your supplier account on Fingerling has been approved';
                $body = "
                    <p>Hi {$owner_name},</p>
                    <p>Your supplier account (business: {$business}) has been approved. You can now log in and start managing your inventory.</p>
                    <p><a href=\"{$login_url}\" style=\"display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;\">Log in now</a></p>
                    {$notes_block}
                    <p>Welcome aboard!<br>The " . APP_NAME . " Team</p>
                ";
                break;

            case 'rejected':
                $subject = 'Your supplier account on Fingerling was not approved';
                $body = "
                    <p>Hi {$owner_name},</p>
                    <p>Your supplier account (business: {$business}) was not approved.</p>
                    {$notes_block}
                    <p>If you would like to reapply or need clarification, please contact support at <a href=\"mailto:{$support_email}\">{$support_email}</a>.</p>
                    <p>Regards,<br>The " . APP_NAME . " Team</p>
                ";
                break;

            case 'suspended':
                $subject = 'Your supplier account has been suspended';
                $appeal_subject = rawurlencode("Appeal for Suspension ({$business})");
                $appeal_body = rawurlencode("I would like to appeal the suspension for {$business}.\n\nMy email: {$email}\n\nReason for appeal: ");
                $appeal_url = "mailto:{$support_email}?subject={$appeal_subject}&body={$appeal_body}";
                $body = "
                    <p>Hi {$owner_name},</p>
                    <p>Your supplier account (business: {$business}) has been suspended.</p>
                    {$notes_block}
                    <p>If you wish to appeal, click the button below to compose an email to our support team. This will open your email client and nothing will be stored on our site.</p>
                    <p><a href=\"{$appeal_url}\" target=\"_blank\" style=\"display:inline-block;padding:12px 22px;background:#dc2626;color:#fff;border-radius:6px;text-decoration:none;\">Submit Appeal</a></p>
                    <p>Thank you,<br>The " . APP_NAME . " Team</p>
                ";
                break;

            case 'reactivated':
                $subject = 'Your supplier account has been reactivated';
                $body = "
                    <p>Hi {$owner_name},</p>
                    <p>Your supplier account (business: {$business}) has been reactivated.</p>
                    {$notes_block}
                    <p>You can now log back in here: <a href=\"{$login_url}\">{$login_url}</a></p>
                    <p>Welcome back!<br>The " . APP_NAME . " Team</p>
                ";
                break;

            default:
                return ['success' => false, 'error' => 'invalid_status'];
        }

        $html = "
            <html>
                <body style=\"font-family:Arial,Helvetica,sans-serif;background-color:#f4f6f8;padding:20px;color:#111827;\">
                    <div style=\"max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,0.08);\">
                        {$body}
                        <hr style=\"margin:24px 0;border:none;border-top:1px solid #e5e7eb;\">
                        <p style=\"font-size:12px;color:#6b7280;\">This is an automated message from " . APP_NAME . ". Please do not reply.</p>
                    </div>
                </body>
            </html>
        ";

        $result = send_app_email($email, $subject, $html, true);
        if (!$result['success']) {
            error_log("send_supplier_status_email: failed to {$status_key} email for supplier {$supplier_id} - {$result['error']}");
        }

        return $result;
    }
}

