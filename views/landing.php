
<?php if (!defined('BASE_URL')) { require_once __DIR__ . '/../config/config.php'; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> - Welcome</title>
<link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
<link rel="alternate icon" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
<link rel="preload" as="image" href="<?php echo base_url('gif/fish.gif'); ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
<link href="<?php echo asset_url('css/style.css'); ?>" rel="stylesheet">
<link href="<?php echo asset_url('css/landing-navbar.css'); ?>" rel="stylesheet">
<style>
:root {
    --brand-gradient: linear-gradient(135deg, #3b82f6, #1d4ed8);
    --brand-blue: #3b82f6;
    --header-bg: rgba(0, 0, 0, 0.25);
    --header-bg-scrolled: rgba(0, 0, 0, 0.7);
}
.hero-section {
    position: relative;
    min-height: 100vh;
    color: #ffffff;
    display: flex;
    align-items: center;
    overflow: hidden;
}
.hero-section::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: url('<?php echo base_url('gif/fish.gif'); ?>');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 1;
    transform: translateZ(0);
}
.hero-section::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
}
.hero-section > .container {
    position: relative;
    z-index: 1;
}
@media (max-width: 991.98px) {
    .hero-section {
        min-height: 100vh;
    }
}
@media (prefers-reduced-motion: reduce) {
    .hero-section::before { display: none; }
    .hero-section::after { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
}
.hero-navbar {
    background: var(--header-bg) !important;
    transition: all 0.3s ease;
    padding: 1rem 0;
}
.hero-navbar.scrolled {
    background: var(--header-bg-scrolled) !important;
    backdrop-filter: saturate(160%) blur(12px);
    padding: 0.5rem 0;
}
.brand-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--brand-gradient);
    color: #fff;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
    flex-shrink: 0;
}
.brand-icon i { font-size: 18px; line-height: 1; }

/* === FIXED: Responsive Navbar Brand Text === */
.navbar-brand {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    white-space: nowrap;
    font-size: 1.25rem;
    flex-wrap: wrap;
}

@media (max-width: 576px) {
    .navbar-brand {
        font-size: 1.1rem;
        gap: 0.5rem;
    }
    .navbar-brand span:last-child {
        white-space: normal;
        line-height: 1.3;
    }
}

@media (max-width: 400px) {
    .navbar-brand {
        font-size: 1rem;
    }
    .brand-icon {
        width: 32px;
        height: 32px;
    }
    .brand-icon i {
        font-size: 16px;
    }
}

/* Custom Toggler */
.navbar-toggler {
    border: none;
    padding: 8px 10px;
    border-radius: 10px;
    background: var(--brand-gradient);
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
    transition: all 0.3s ease;
}
.navbar-toggler:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
}
.navbar-toggler:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}
.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    width: 26px;
    height: 26px;
}

/* FIXED MOBILE MENU - NO HORIZONTAL SCROLL, DARK, CENTERED, CLICK OUTSIDE */
@media (max-width: 991.98px) {
    .navbar-collapse {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) scale(0.95) !important;
        width: 90vw !important;
        max-width: 400px !important;
        max-height: 92vh !important;
        background: rgba(15, 23, 42, 0.98) !important;
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 2.5rem 2rem;
        box-shadow: 0 30px 60px rgba(0,0,0,0.8);
        opacity: 0;
        visibility: hidden;
        transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        z-index: 9999;
        overflow-y: auto;
    }
    .navbar-collapse.show {
        opacity: 1;
        visibility: visible;
        transform: translate(-50%, -50%) scale(1) !important;
    }
    .navbar::after {
        content: "";
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.4s;
    }
    .navbar.show::after {
        opacity: 1;
        visibility: visible;
    }
    .navbar-nav {
        text-align: center;
    }
    .navbar-nav .nav-link {
        font-size: 1.25rem;
        font-weight: 500;
        color: #e2e8f0 !important;
        padding: 1rem 1.5rem !important;
        border-radius: 16px;
        margin: 0.5rem 0;
        display: block;
        transition: all 0.3s;
    }
    .navbar-nav .nav-link:hover {
        background: rgba(255,255,255,0.12);
        color: #ffffff !important;
    }
    .navbar-nav .btn {
        margin: 2rem auto 0;
        display: block;
        width: fit-content;
        padding: 1rem 3rem;
        font-size: 1.15rem;
        border-radius: 50px;
    }
}

html, body { overflow-x: hidden; }

.feature-img {
    height: 220px;
    object-fit: cover;
}
@media (max-width: 575.98px){
    .feature-img{ height: 180px; }
}
.global-footer {
    background:#0f172a;
    color:#e5e7eb;
    padding: 3rem 0 1.25rem;
    margin-top: 2rem;
}
.global-footer .container {
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1rem;
}
.footer-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr 1fr;
    gap: 2rem;
    align-items: start;
}
.brand-badge {
    width:40px;
    height:40px;
    border-radius:10px;
    background: var(--brand-gradient);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 6px 18px rgba(37,99,235,.35);
    color:#fff;
}
.brand-badge i{ font-size:18px; }
.brand-name {
    margin: .75rem 0 .5rem;
    font-weight:800;
}
.brand-desc {
    color:#cbd5e1;
    margin:0;
}
.footer-links h6, .footer-contact h6{
    font-weight:700;
    margin-bottom:.75rem;
}
.footer-links ul, .footer-contact ul{
    list-style:none;
    padding:0;
    margin:0;
}
.footer-links li{ margin:.4rem 0; }
.footer-links a{
    color:#e5e7eb;
    text-decoration:none;
}
.footer-links a:hover{
    color:#fff;
    text-decoration:underline;
}
.contact-list li{
    margin:.4rem 0;
    display:flex;
    align-items:center;
    gap:.5rem;
    color:#cbd5e1;
}
.footer-divider{
    border-color: rgba(255,255,255,.08);
    margin:1.5rem 0;
}
.footer-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:#cbd5e1;
    font-size:.95rem;
}
.footer-legal{
    display:flex;
    align-items:center;
    gap:.5rem;
}
.footer-legal a{
    color:#e5e7eb;
    text-decoration:none;
}
.footer-legal a:hover{
    color:#fff;
    text-decoration:underline;
}
@media (max-width: 991.98px){
    .footer-grid{ grid-template-columns: 1fr 1fr; }
}
@media (max-width: 575.98px){
    .footer-grid{ grid-template-columns: 1fr; }
    .footer-bar{ flex-direction:column; gap:.5rem; text-align:center; }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top hero-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo base_url(); ?>">
            <span class="brand-icon"><i class="fas fa-fish"></i></span>
            <span><?php echo APP_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#stats">Statistics</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimonials">Reviews</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo base_url('auth/login.php'); ?>"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li class="nav-item">
                    <a class="btn btn-outline-light" href="<?php echo base_url('auth/register.php'); ?>">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <h1 class="display-3 fw-bold mb-4"> Fresh, Certified <span class="text-warning">Fingerlings</span> — For Tangub City Delivery Only </h1>
                <p class="lead mb-4 fs-5"> Connect easily with verified suppliers in Tangub City, explore a wide selection of fresh and certified fingerling species with complete details, and place your orders with full confidence knowing your stocks will be delivered safely and on time within the city. </p>
                <div class="d-flex flex-column flex-md-row gap-3 mb-4">
                    <a href="<?php echo base_url('auth/register.php?type=customer'); ?>" class="btn btn-warning btn-lg px-4 py-3">
                        <i class="fas fa-shopping-cart"></i> Start Shopping
                    </a>
                    <a href="<?php echo base_url('auth/register.php?type=supplier'); ?>" class="btn btn-outline-light btn-lg px-4 py-3">
                        <i class="fas fa-store"></i> Become a Supplier
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rest of your sections remain unchanged -->
<section id="features" class="py-5">
    <div class="container">
        <div class="row text-center mb-4">
            <div class="col-lg-8 mx-auto" data-aos="fade-up">
                <h2 class="display-6 fw-bold mb-2">What You Can Expect</h2>
                <p class="lead text-muted">Fingerling Online Ordering Platform is </p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="50">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="<?php echo base_url('images/img1.jpeg'); ?>" class="card-img-top feature-img" alt="Fresh stock ready for delivery">
                    <div class="card-body">
                        <h5 class="card-title">Fresh Stock</h5>
                        <p class="card-text text-muted">Healthy fingerlings sourced from trusted and verified suppliers in Tangub City, providing customers with certified quality stocks that meet aquaculture standards and are available for safe and reliable delivery within the city.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="150">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="<?php echo base_url('images/img2.jpeg'); ?>" class="card-img-top feature-img" alt="Quality sorting and preparation">
                    <div class="card-body">
                        <h5 class="card-title">Available Fingerling Sizes</h5>
                        <p class="card-text text-muted">Fingerlings are available in different sizes, giving customers the flexibility to choose the most suitable stock for their specific aquaculture needs, whether for small-scale ponds, medium-sized setups, or larger commercial operations.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="250">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="<?php echo base_url('images/img3.jpg'); ?>" class="card-img-top feature-img" alt="Reliable transport and logistics">
                    <div class="card-body">
                        <h5 class="card-title">Reliable Handling</h5>
                        <p class="card-text text-muted">Fingerlings packed in plastic bags and prepared for distribution, ensuring safe handling and reliable delivery for aquaculture stocking needs in Tangub City.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="testimonials" class="py-5 bg-light">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-lg-8 mx-auto" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">What Our Users Say</h2>
                <p class="lead text-muted">Real feedback from our satisfied customers and suppliers</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="100">
                <div class="testimonial-card card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"This platform revolutionized my fish farming business. Easy to find quality suppliers and the delivery is always on time!"</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Maria Santos</h6>
                                <small class="text-muted">Fish Farm Owner, Batangas</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="200">
                <div class="testimonial-card card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"As a supplier, this platform helped me reach more customers nationwide. The analytics dashboard is incredibly helpful!"</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-store"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Juan Dela Cruz</h6>
                                <small class="text-muted">Supplier, Pangasinan</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="300">
                <div class="testimonial-card card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"Excellent platform! The quality assurance and customer support are top-notch. Highly recommended for aquaculture businesses."</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-fish"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Roberto Aquino</h6>
                                <small class="text-muted">Aquaculture Expert, Laguna</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="about" class="py-5">
    <div class="container">
        <div class="row align-items-start">
            <div class="col-lg-7" data-aos="fade-right">
                <h2 class="display-5 fw-bold mb-4">About Fingerling Online Ordering Platform</h2>
                <p class="lead mb-3">We connect fish farmers and suppliers across the Philippines with a simple, modern platform built around trust and clarity.</p>
                <ul class="list-unstyled mb-4">
                    <li class="mb-2"><i class="fas fa-check text-warning me-2"></i> Verified supplier profiles</li>
                    <li class="mb-2"><i class="fas fa-check text-warning me-2"></i> Clear, comparable offers</li>
                    <li class="mb-2"><i class="fas fa-check text-warning me-2"></i> Order updates and tracking</li>
                    <li class="mb-2"><i class="fas fa-check text-warning me-2"></i> Support that understands aquaculture</li>
                </ul>
                <a href="<?php echo base_url('auth/register.php'); ?>" class="btn btn-warning btn-lg">
                    <i class="fas fa-user-plus me-2"></i> Get Started
                </a>
            </div>
            <div class="col-lg-5" data-aos="fade-left">
                <div class="p-4 rounded-3" style="background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);">
                    <h5 class="mb-2"><i class="fas fa-seedling text-warning me-2"></i> Built for growers</h5>
                    <p class="mb-0 text-light">Whether you're scaling a farm or getting started, the tools here are designed to be practical and straightforward.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="global-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="brand-badge"><i class="fas fa-fish"></i></div>
                <h5 class="brand-name"><?php echo APP_NAME; ?></h5>
                <p class="brand-desc">Connecting Filipino fish farmers with verified suppliers. Buy, sell, and grow with confidence.</p>
            </div>
            <div class="footer-links">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="<?php echo base_url(); ?>">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="<?php echo base_url('auth/login.php'); ?>">Login</a></li>
                    <li><a href="<?php echo base_url('auth/register.php'); ?>">Register</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h6>Contact</h6>
                <ul class="contact-list">
                    <li><i class="fas fa-envelope"></i> fingerlingservice@gmail.com</li>
                    <li><i class="fas fa-phone"></i> +63 9627058707</li>
                    <li><i class="fas fa-location-dot"></i> Philippines</li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider" />
        <div class="footer-bar">
            <span>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
            <div class="footer-legal">
                <a href="#">Privacy</a>
                <span>•</span>
                <a href="#">Terms</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script src="<?php echo asset_url('js/landing-navbar.js'); ?>"></script>
<script>
    AOS.init({
        duration: 1000,
        once: true,
        offset: 100
    });
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.hero-navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>
</body>
</html>