<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    http_response_code(403);
    die('Access denied');
}

try {
    $database = new Database();
    $user_id = get_user_id();
    $user = new User($database);
    $profile = $user->getUserProfile($user_id);

    if ($profile['status'] !== 'approved') {
        http_response_code(403);
        die('Access denied');
    }

    $supplier_id = $profile['id'];
    $supplier = new Supplier($database, $supplier_id);

    // Get parameters
    $period = $_GET['period'] ?? 'month';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    // Get revenue data (fetch all records with large limit)
    // method signature: getRevenueTracking($period, $page, $per_page, $start_date, $end_date)
    $revenue_data = $supplier->getRevenueTracking($period, 1, 100000, $start_date, $end_date);
    $transactions = $revenue_data['transactions'] ?? [];
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="revenue_report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Create simple CSV format (can be opened in Excel)
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="revenue_report_' . date('Y-m-d') . '.csv"');
    
    // Output CSV data
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Order ID', 'Order Number', 'Customer Name', 'Amount (₱)', 'Service Fee (₱)', 'Earnings (₱)', 'Status', 'Payment Method', 'Date']);
    
    // Add data rows
    foreach ($transactions as $transaction) {
        $service_fee = $transaction['service_fee'] ?? 0;
        $earnings = $transaction['earnings'] ?? 0;
        
        fputcsv($output, [
            $transaction['id'],
            $transaction['order_number'],
            $transaction['first_name'] . ' ' . $transaction['last_name'],
            number_format($transaction['total_amount'], 2),
            number_format($service_fee, 2),
            number_format($earnings, 2),
            ucfirst(str_replace('_', ' ', $transaction['status'])),
            !empty($transaction['payment_method']) ? ucfirst($transaction['payment_method']) : 'N/A',
            date('M j, Y g:i A', strtotime($transaction['created_at']))
        ]);
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error generating report: ' . $e->getMessage());
}
?>