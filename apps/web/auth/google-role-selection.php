<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user_type = get_user_type();
    switch ($user_type) {
        case 'admin':
            redirect(base_url('admin/dashboard.php'));
            break;
        case 'supplier':
            redirect(base_url('supplier/dashboard.php'));
            break;
        case 'customer':
            redirect(base_url('customer/dashboard.php'));
            break;
    }
}

// Check if Google OAuth is configured
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    $_SESSION['error'] = 'Google OAuth is not configured.';
    redirect(base_url('auth/login.php'));
}

// Generate state parameter for security
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_role_selection'] = true; // Flag to indicate we want role selection

// Google OAuth URL for customer
$google_oauth_url_customer = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'scope' => 'email profile',
    'response_type' => 'code',
    'state' => $state,
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

// Google OAuth URL for supplier (same URL, but we'll handle the role in the callback)
$google_oauth_url_supplier = $google_oauth_url_customer;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role - <?php echo APP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link rel="alternate icon" href="<?php echo base_url('assets/icons/fish.svg'); ?>">

    <!-- Fonts & Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-500: #3b82f6;
            --primary-700: #1d4ed8;
            --brand-accent: #f59e0b; /* warm yellow for CTA */
            --muted: #9ca3af;
        }

        html, body { height: 100%; }
        body { margin: 0; }

        /* Header blends with background GIF */
        .hero-navbar {
            background: rgba(0, 0, 0, 0.25) !important;
            transition: background .2s ease, backdrop-filter .2s ease;
        }
        .hero-navbar.scrolled {
            background: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: saturate(160%) blur(8px);
        }
        .brand-icon { 
            width: 36px; height: 36px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700)); color: #fff;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }
        .navbar-brand { font-weight: 700; }

        /* Hero section */
        .auth-hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            color: #fff;
            overflow: hidden;
            padding-top: 80px;
        }
        .auth-hero::before {
            content: ""; position: absolute; inset: 0;
            background-image: url('<?php echo base_url('gif/fish.gif'); ?>');
            background-size: cover; background-position: center; background-repeat: no-repeat;
            opacity: 1;
            transform: translateZ(0);
        }
        .auth-hero::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.35), rgba(2, 6, 23, 0.35));
        }
        .auth-hero > .container { position: relative; z-index: 1; }

        /* Minimal form (no box/card) */
        .auth-form { width: 100%; max-width: 480px; }
        .auth-title { font-weight: 800; letter-spacing: .3px; }
        .auth-subtitle { color: #e5e7eb; }

        .form-label { color: #e5e7eb; font-weight: 600; }
        .input-group-text { background: rgba(255,255,255,.9); border: 0; }
        .form-control {
            background: rgba(255,255,255,.9);
            border: 0; color: #0f172a; padding: .9rem 1rem;
            border-radius: 12px;
        }
        .form-control:focus { box-shadow: 0 0 0 .2rem rgba(59,130,246,.2); }
        .btn-accent {
            background: linear-gradient(135deg, var(--brand-accent), #d97706);
            border: 0; color: #111827; font-weight: 800; border-radius: 12px; padding: .9rem 1rem;
            box-shadow: 0 12px 24px rgba(245, 158, 11, 0.25);
        }
        .btn-accent:hover { filter: brightness(1.05); }
        .btn-outline-light { border-width: 2px; }

        .links-row a { color: #e5e7eb; text-decoration: none; }
        .links-row a:hover { color: #fff; }

        .helper { color: var(--muted); }

        @media (max-width: 991.98px) {
            .auth-hero { align-items: flex-start; padding-top: calc(64px + 2rem); }
        }

        /* Accessibility: reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .auth-hero::before { display: none; }
            .auth-hero::after { background: linear-gradient(135deg, #0f172a, #1f2937); }
        }
        
        .role-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .role-card.selected {
            border-color: var(--primary-500);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-500);
        }
        
        .role-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }
        
        .role-description {
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<!-- Header / Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top hero-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url(); ?>">
      <span class="brand-icon"><i class="fas fa-fish"></i></span>
      <span><?php echo APP_NAME; ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url(); ?>">Home</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Register</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?php echo base_url('auth/register.php?type=customer'); ?>">Customer</a></li>
            <li><a class="dropdown-item" href="<?php echo base_url('auth/register.php?type=supplier'); ?>">Supplier</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url('auth/login.php'); ?>">Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero with inline form -->
<section class="auth-hero">
  <div class="container">
    <div class="row justify-content-start">
      <div class="col-12 col-md-8 col-lg-6">

        <div class="auth-form">
          <h1 class="display-5 auth-title mb-2">Choose Your Role</h1>
          <p class="auth-subtitle mb-4">Select how you want to use <?php echo APP_NAME; ?> with your Google account.</p>

          <div class="d-grid gap-3">
            <a href="<?php echo $google_oauth_url_customer; ?>&role=customer" class="role-card text-decoration-none">
              <div class="text-center">
                <div class="role-icon">
                  <i class="fas fa-user"></i>
                </div>
                <h3 class="role-title">Continue as Customer</h3>
                <p class="role-description">Buy fingerlings and other aquaculture products from verified suppliers</p>
              </div>
            </a>
            
            <a href="<?php echo $google_oauth_url_supplier; ?>&role=supplier" class="role-card text-decoration-none">
              <div class="text-center">
                <div class="role-icon">
                  <i class="fas fa-store"></i>
                </div>
                <h3 class="role-title">Continue as Supplier</h3>
                <p class="role-description">Sell fingerlings and aquaculture products to local customers</p>
              </div>
            </a>
          </div>

          <div class="mt-4 text-center">
            <a href="<?php echo base_url('auth/login.php'); ?>" class="text-decoration-none">
              <i class="fas fa-arrow-left me-1"></i> Back to Login
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Add selection effect to role cards
    document.querySelectorAll('.role-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Remove selected class from all cards
            document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
            // Add selected class to clicked card
            this.classList.add('selected');
        });
    });
</script>

</body>
</html>