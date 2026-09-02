<?php
// Ensure session is started
session_start();

// Include necessary files
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../classes/User.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user = new User();
    $loginResult = $user->login($username, $password);
    
    if ($loginResult) {
        // Login successful - redirect to dashboard
        header('Location: ../dashboard.php');
        exit();
    } else {
        // Login failed - set error message
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fingerling Online Ordering Platform</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0a0a0a;
            color: white;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 5px;
        }
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .logo {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        .welcome-text {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
        }
        .fish-image {
            position: absolute;
            right: 20%;
            bottom: 20%;
            width: 400px;
            height: 250px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/logo.png" alt="Logo" class="logo">
                Fingerling Online Ordering Platform
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="registerDropdown" role="button" data-bs-toggle="dropdown">
                            Register
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="registerDropdown">
                            <li><a class="dropdown-item" href="../auth/register_supplier.php">Supplier</a></li>
                            <li><a class="dropdown-item" href="../auth/register_customer.php">Customer</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="login-container">
                    <h1 class="welcome-text">Welcome back</h1>
                    <p class="subtitle">Sign in to continue to Fingerling Online Ordering Platform.</p>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger mt-3">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign In</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="../auth/register_customer.php">Register now</a></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <img src="../assets/images/fish.png" alt="Fingerling Fish" class="fish-image">
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>