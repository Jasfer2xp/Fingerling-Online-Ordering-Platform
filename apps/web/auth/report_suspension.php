<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function force_logout() {
    unset(
        $_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['session_id'],
        $_SESSION['otp_verified'], $_SESSION['otp_verified_at'],
        $_SESSION['2fa_user_id'], $_SESSION['2fa_user_type'], $_SESSION['2fa_email'],
        $_SESSION['2fa_remember'], $_SESSION['2fa_remember_token'],
        $_SESSION['suspended_supplier_id'], $_SESSION['suspension_reason']
    );
    session_regenerate_id(true);
}

if (is_logged_in()) {
    $user_type = get_user_type();
    if ($user_type === 'supplier') {
        $stmt = $pdo->prepare("SELECT id, status, suspension_reason FROM suppliers WHERE user_id = ?");
        $stmt->execute([get_user_id()]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($supplier && $supplier['status'] === 'suspended') {
            $appeal_check = $pdo->prepare("SELECT 1 FROM supplier_appeals WHERE supplier_id = ? LIMIT 1");
            $appeal_check->execute([$supplier['id']]);

            if ($appeal_check->fetch()) {
                force_logout();
                $message = "Your account is suspended. You have already submitted an appeal. It is under review. Please check your email for updates.";
                $show_form = false;
            } else {
                $show_form = true;
                $supplier_id = $supplier['id'];
                $suspension_reason = $supplier['suspension_reason'] ?? 'No reason provided.';
            }
        } else {
            redirect(base_url('supplier/dashboard.php'));
        }
    } else {
        redirect(base_url($user_type . '/dashboard.php'));
    }
}
elseif (isset($_SESSION['suspended_supplier_id'])) {
    $supplier_id = $_SESSION['suspended_supplier_id'];
    $appeal_check = $pdo->prepare("SELECT 1 FROM supplier_appeals WHERE supplier_id = ? LIMIT 1");
    $appeal_check->execute([$supplier_id]);

    if ($appeal_check->fetch()) {
        force_logout();
        $message = "Your account is suspended. You have already submitted an appeal. It is under review. Please check your email for updates.";
        $show_form = false;
    } else {
        $show_form = true;
        $suspension_reason = $_SESSION['suspension_reason'] ?? 'No reason provided.';
    }
} else {
    redirect(base_url('auth/login.php'));
}

if ($show_form && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $appeal_message = trim($_POST['appeal_message'] ?? '');

    if (empty($appeal_message)) {
        $error = "Please explain why your account should be reinstated.";
    } elseif (strlen($appeal_message) < 50) {
        $error = "Appeal message must be at least 50 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO supplier_appeals (supplier_id, appeal_message, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->execute([$supplier_id, $appeal_message]);

            send_app_email("admin@yoursite.com", "New Supplier Appeal", "Supplier ID #$supplier_id submitted an appeal.");

            $success = "Your appeal has been submitted successfully. We will review it and contact you via email.";
            $show_form = false;
            unset($_SESSION['suspended_supplier_id'], $_SESSION['suspension_reason']);
            force_logout();
        } catch (Exception $e) {
            $error = "Failed to submit appeal. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border: #334155;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --primary: #3b82f6;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 1rem;
        }

        .suspension-container {
            max-width: 560px;
            margin: 0 auto;
            width: 100%;
        }

        .suspension-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px);
        }

        .card-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .card-header i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .card-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
        }

        .card-body {
            padding: 2.5rem;
        }

        .reason-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .reason-box strong {
            color: #fca5a5;
            font-weight: 700;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .form-control, .form-control:focus {
            background: #334155;
            border: 1px solid #475569;
            color: white;
            border-radius: 1rem;
            padding: 0.9rem 1.2rem;
            font-size: 1rem;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .btn-submit {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: #111827;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 1rem 2rem;
            border-radius: 1rem;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(245, 158, 11, 0.4);
        }

        .btn-back {
            background: transparent;
            border: 2px solid #475569;
            color: var(--text-secondary);
            padding: 0.9rem 2rem;
            border-radius: 1rem;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: rgba(148, 163, 184, 0.1);
            border-color: #94a3b8;
            color: white;
        }

        .alert {
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .alert-warning {
            background: rgba(251, 191, 36, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.3);
            color: #fde047;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .support-text {
            text-align: center;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .support-text a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }

        .support-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .card-header { padding: 1.5rem; }
            .card-header h1 { font-size: 1.6rem; }
            .card-body { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="suspension-container">
        <div class="suspension-card">
            <div class="card-header">
                <i class="ri-shield-user-line"></i>
                <h1>Account Suspended</h1>
            </div>

            <div class="card-body">

                <?php if (isset($message)): ?>
                    <div class="alert alert-warning">
                        <i class="ri-information-line me-2" style="font-size: 1.5rem;"></i>
                        <strong><?php echo nl2br(htmlspecialchars($message)); ?></strong>
                    </div>
                    <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-back">
                        <i class="ri-arrow-left-line me-2"></i> Back to Login
                    </a>

                <?php elseif (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="ri-checkbox-circle-line me-2" style="font-size: 1.5rem;"></i>
                        <strong><?php echo htmlspecialchars($success); ?></strong>
                    </div>
                    <a href="<?php echo base_url('auth/login.php'); ?>" class="btn-submit">
                        <i class="ri-login-box-line me-2"></i> Back to Login
                    </a>

                <?php else: ?>
                    <div class="reason-box">
                        <strong>Suspension Reason:</strong><br><br>
                        <?php echo nl2br(htmlspecialchars($suspension_reason)); ?>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">
                                Explain why your account should be reinstated
                            </label>
                            <textarea
                                name="appeal_message"
                                class="form-control"
                                rows="7"
                                placeholder="Please be honest and detailed. Include:&#10;• When did the issue occur?&#10;• What steps have you taken to fix it?&#10;• Why should we trust you again?"
                                required
                                minlength="50"><?php echo htmlspecialchars($_POST['appeal_message'] ?? ''); ?></textarea>
                            <small class="text-muted mt-2 d-block">
                                Minimum 50 characters • Be sincere for faster review
                            </small>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn-submit">
                                <i class="ri-send-plane-fill me-2"></i>
                                Submit Appeal
                            </button>
                            <a href="<?php echo base_url('auth/login.php'); ?>" class="btn-back">
                                <i class="ri-arrow-left-line me-2"></i>
                                Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="support-text">
            Need immediate help? Contact support: 
            <a href="mailto:support@yoursite.com">support@yoursite.com</a>
        </div>
    </div>
</body>
</html>