<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$database = new Database();
$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Get date range from query params
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
$start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
$end_dt = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$start_dt || !$end_dt) {
    die('Invalid date format');
}

// Get analytics data
$species_breakdown = $supplier->getSpeciesSalesBreakdown($start_date, $end_date);
$historical_inventory = $supplier->getHistoricalInventory($start_date, $end_date);
$earnings = $supplier->getEarningsBreakdown($start_date, $end_date);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sales_report_' . $start_date . '_to_' . $end_date . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write Sales Summary
fputcsv($output, ['SALES REPORT']);
fputcsv($output, ['Period:', $start_date . ' to ' . $end_date]);
fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
fputcsv($output, []);

// Write Summary Stats
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Revenue', number_format($earnings['summary']['total_revenue'] ?? 0, 2)]);
fputcsv($output, ['Total Orders', $earnings['summary']['total_orders'] ?? 0]);
fputcsv($output, ['Average Order Value', number_format($earnings['summary']['avg_order_value'] ?? 0, 2)]);
fputcsv($output, ['Unique Customers', $earnings['summary']['unique_customers'] ?? 0]);
fputcsv($output, ['Species Sold', $earnings['summary']['species_sold'] ?? 0]);
fputcsv($output, []);

// Write Species Sales Breakdown
fputcsv($output, ['SPECIES SALES BREAKDOWN']);
fputcsv($output, ['Species Name', 'Scientific Name', 'Size Category', 'Quantity Sold', 'Revenue', 'Average Price', 'Order Count', 'First Sale', 'Last Sale']);

if (!empty($species_breakdown)) {
    foreach ($species_breakdown as $item) {
        fputcsv($output, [
            $item['species_name'],
            $item['scientific_name'] ?? '',
            $item['size_category'] ?? 'N/A',
            $item['total_quantity_sold'],
            number_format($item['total_revenue'], 2),
            number_format($item['average_price'], 2),
            $item['order_count'],
            date('Y-m-d', strtotime($item['first_sale_date'])),
            date('Y-m-d', strtotime($item['last_sale_date']))
        ]);
    }
}
fputcsv($output, []);

// Write Historical Inventory
fputcsv($output, ['HISTORICAL INVENTORY']);
fputcsv($output, ['Species Name', 'Scientific Name', 'Size Category', 'Current Stock', 'Current Price', 'Date Added', 'Last Updated', 'Sold in Period', 'Revenue in Period', 'Status']);

if (!empty($historical_inventory)) {
    foreach ($historical_inventory as $item) {
        fputcsv($output, [
            $item['species_name'],
            $item['scientific_name'] ?? '',
            $item['size_category'] ?? 'N/A',
            $item['current_stock'],
            number_format($item['current_price'], 2),
            date('Y-m-d', strtotime($item['date_added'])),
            date('Y-m-d', strtotime($item['last_updated'])),
            $item['total_sold_in_period'],
            number_format($item['revenue_in_period'], 2),
            ucfirst(str_replace('_', ' ', $item['availability_status']))
        ]);
    }
}
fputcsv($output, []);

// Write Monthly Earnings
fputcsv($output, ['MONTHLY EARNINGS BREAKDOWN']);
fputcsv($output, ['Month', 'Orders', 'Revenue']);

if (!empty($earnings['monthly_revenue'])) {
    foreach ($earnings['monthly_revenue'] as $month) {
        fputcsv($output, [
            $month['month_name'],
            $month['orders'],
            number_format($month['revenue'], 2)
        ]);
    }
}
fputcsv($output, []);

// Write Revenue by Species
fputcsv($output, ['REVENUE BY SPECIES']);
fputcsv($output, ['Species Name', 'Revenue', 'Percentage']);

if (!empty($earnings['species_revenue'])) {
    foreach ($earnings['species_revenue'] as $species) {
        fputcsv($output, [
            $species['species_name'],
            number_format($species['revenue'], 2),
            number_format($species['percentage'], 2) . '%'
        ]);
    }
}

fclose($output);
exit();
?>
