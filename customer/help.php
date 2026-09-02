<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

$page_title = 'Help Center';
include '../includes/customer_header.php';
?>

<!-- ===================== Help Center (Dark Theme Redesign) ===================== -->
<main class="role-main-content customer-main-content">
  <div class="content-wrap">

    <!-- Header -->
    <div class="page-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-4">
      <div class="d-flex align-items-center mb-3 mb-md-0">
        <div class="page-icon me-3"><i class="fas fa-question-circle"></i></div>
        <div>
          <h1 class="page-title mb-0">Help Center</h1>
          <div class="text-white small">Find answers or contact our support team</div>
        </div>
      </div>
    </div>

    <!-- Search Help -->
    <div class="card modern-card p-3 mb-4">
      <div class="input-group">
        <span class="input-group-text bg-transparent text-light border-secondary">
          <i class="fas fa-search"></i>
        </span>
        <input type="text" class="form-control bg-transparent text-white border-secondary" placeholder="Search for help topics..." id="helpSearch" style="color: white;">
      </div>
    </div>

    <!-- FAQ Categories -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card supplier-card h-100">
          <div class="card-body">
            <div class="text-center mb-3">
              <i class="fas fa-shopping-cart fa-3x text-primary mb-3"></i>
              <h4 class="text-dark">Shopping & Orders</h4>
            </div>
            <ul class="list-unstyled text-center">
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">How to place an order</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Payment methods</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Order tracking</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Cancellation policy</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card supplier-card h-100">
          <div class="card-body">
            <div class="text-center mb-3">
              <i class="fas fa-user fa-3x text-success mb-3"></i>
              <h4 class="text-dark">Account & Profile</h4>
            </div>
            <ul class="list-unstyled text-center">
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Update profile information</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Change password</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Manage addresses</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Account security</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card supplier-card h-100">
          <div class="card-body">
            <div class="text-center mb-3">
              <i class="fas fa-fish fa-3x text-info mb-3"></i>
              <h4 class="text-dark">Products & Quality</h4>
            </div>
            <ul class="list-unstyled text-center">
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Product quality standards</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Care instructions</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Return policy</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Product reviews</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card supplier-card h-100">
          <div class="card-body">
            <div class="text-center mb-3">
              <i class="fas fa-headset fa-3x text-warning mb-3"></i>
              <h4 class="text-dark">Support</h4>
            </div>
            <ul class="list-unstyled text-center">
              <li class="mb-2"><a href="contact.php" class="text-decoration-none text-muted hover-link">Contact support</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Report an issue</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Technical problems</a></li>
              <li class="mb-2"><a href="#" class="text-decoration-none text-muted hover-link">Feedback</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Information -->
    <div class="card supplier-card mt-4">
      <div class="card-body">
        <h4 class="text-dark mb-4 text-center"><i class="fas fa-phone me-2 text-primary"></i>Need More Help?</h4>
        <div class="row text-center">
          <div class="col-md-4 mb-3 mb-md-0">
            <i class="fas fa-envelope fa-2x text-primary mb-2"></i>
            <h6 class="text-dark">Email Support</h6>
            <p class="text-muted">support@fingerlingmarketplace.com</p>
          </div>
          <div class="col-md-4 mb-3 mb-md-0">
            <i class="fas fa-phone fa-2x text-success mb-2"></i>
            <h6 class="text-dark">Phone Support</h6>
            <p class="text-muted">+63 123 456 7890</p>
          </div>
          <div class="col-md-4">
            <i class="fas fa-clock fa-2x text-info mb-2"></i>
            <h6 class="text-dark">Business Hours</h6>
            <p class="text-muted">Mon-Fri: 8AM–6PM</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<!-- ===================== Styles (Dark Theme Match) ===================== -->
<style>
  :root {
    --bg-dark: #0b0b0b;
    --panel: #0f1720;
    --muted: #9aa4b2;
    --accent: #0ea5a4;
    --card-bg: #ffffff;
  }

  body, html {
    background: var(--bg-dark) !important;
    color: #e6eef6;
  }

  .content-wrap {
    max-width: 1200px;
    margin: 28px auto;
    padding: 0 20px;
  }

  .page-header .page-icon {
    font-size: 1.6rem;
    color: var(--accent);
    background: rgba(14,165,164,0.06);
    padding: 10px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .page-title {
    color: #fff;
    font-size: 1.35rem;
  }

  .modern-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.05);
    color: #e6eef6;
  }

  .supplier-card {
    background: var(--card-bg);
    border-radius: 12px;
    border: none;
    overflow: hidden;
    transition: transform .22s ease, box-shadow .22s ease;
  }

  .supplier-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 45px rgba(2,6,23,0.6);
  }

  .supplier-card .card-body {
    padding: 20px;
  }

  .hover-link {
    color: #6b7280;
    transition: color 0.2s;
  }
  .hover-link:hover {
    color: var(--accent);
  }

  .input-group .form-control {
    background-color: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
  }

  .input-group-text {
    border: 1px solid rgba(255,255,255,0.1);
  }

  @media (max-width: 767px) {
    .content-wrap {
      padding: 0 14px;
    }
  }
</style>

<?php include '../includes/customer_footer.php'; ?>
