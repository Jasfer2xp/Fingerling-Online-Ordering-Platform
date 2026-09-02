/* Ensure admin dashboard has proper styling */
.admin-main-content {
    margin-left: 260px;
    padding: 20px;
    transition: margin-left 0.3s ease;
    min-height: calc(100vh - 100px);
}

@media (max-width: 992px) {
    .admin-main-content {
        margin-left: 0;
    }
}

/* Ensure cards have proper styling */
.modern-stat-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 20px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e2e8f0;
}

.modern-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

/* Ensure dark mode works properly */
[data-theme="dark"] .modern-stat-card {
    background: #1e293b;
    color: #f8fafc;
    border-color: #334155;
}

.modern-stat-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    font-size: 24px;
    color: white;
}

.bg-primary {
    background-color: #3b82f6 !important;
}

.bg-success {
    background-color: #10b981 !important;
}

.bg-info {
    background-color: #0ea5e9 !important;
}

.bg-warning {
    background-color: #f59e0b !important;
}

.bg-danger {
    background-color: #ef4444 !important;
}

.text-primary {
    color: #3b82f6 !important;
}

.text-success {
    color: #10b981 !important;
}

.text-info {
    color: #0ea5e9 !important;
}

.text-warning {
    color: #f59e0b !important;
}

.text-danger {
    color: #ef4444 !important;
}
<!DOCTYPE html>
<html lang="en" data-theme="light" data-role="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
 echo $page_title; ?> - <?php echo APP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link rel="alternate icon" href="<?php echo base_url('assets/icons/fish.svg'); ?>">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">

    <!-- Modern CSS Framework -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/modern-framework.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/role-themes.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/modern-sidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/modern-dashboard.css'); ?>">
    <!-- Removed dark-mode.css as we're not using dark mode functionality -->

    <!-- Additional required CSS files -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">

    <!-- jQuery (must be loaded first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <!-- Additional Admin Styles -->
    <style>
        /* Ensure admin dashboard has proper styling */
        .admin-main-content {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 100px);
        }
        
        @media (max-width: 992px) {
            .admin-main-content {
                margin-left: 0;
            }
        }
        
        /* Ensure cards have proper styling */
        .modern-stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .modern-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Ensure dark mode works properly */
        [data-theme="dark"] .modern-stat-card {
            background: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }
        
        .modern-stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }
        
        .bg-primary {
            background-color: #3b82f6 !important;
        }
        
        .bg-success {
            background-color: #10b981 !important;
        }
        
        .bg-info {
            background-color: #0ea5e9 !important;
        }
        
        .bg-warning {
            background-color: #f59e0b !important;
        }
        
        .bg-danger {
            background-color: #ef4444 !important;
        }
        
        .text-primary {
            color: #3b82f6 !important;
        }
        
        .text-success {
            color: #10b981 !important;
        }
        
        .text-info {
            color: #0ea5e9 !important;
        }
        
        .text-warning {
            color: #f59e0b !important;
        }
        
        .text-danger {
            color: #ef4444 !important;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
