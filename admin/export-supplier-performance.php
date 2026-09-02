<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get parameters
$format = $_GET['format'] ?? 'pdf';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$supplier_id = $_GET['supplier_id'] ?? '';

// Validate format
if (!in_array($format, ['pdf', 'excel'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid format']);
    exit;
}

try {
    // Get supplier performance data
    $sql = "SELECT 
                s.id,
                s.business_name,
                s.owner_name,
                s.contact_number,
                s.city,
                s.province,
                s.rating,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END) as avg_order_value,
                COUNT(DISTINCT o.customer_id) as unique_customers,
                COUNT(DISTINCT i.id) as total_products,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                (SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) / COUNT(o.id) * 100) as completion_rate
            FROM suppliers s
            LEFT JOIN orders o ON s.id = o.supplier_id AND DATE(o.created_at) BETWEEN ? AND ?
            LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
            WHERE s.status = 'approved'";
    
    $params = [$date_from, $date_to];
    
    if (!empty($supplier_id)) {
        $sql .= " AND s.id = ?";
        $params[] = $supplier_id;
    }
    
    $sql .= " GROUP BY s.id ORDER BY total_revenue DESC";
    
    $suppliers = $database->fetchAll($sql, $params);
    
    if (empty($suppliers)) {
        echo json_encode(['success' => false, 'message' => 'No supplier data found for the selected period']);
        exit;
    }

    if ($format === 'pdf') {
        // Generate HTML that can be printed as PDF
        $filename = 'supplier_performance_' . date('Y-m-d_H-i-s') . '.html';

        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Supplier Performance Report</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .info { margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #343a40; color: white; font-weight: bold; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                tr:nth-child(even) { background-color: #f2f2f2; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Supplier Performance Report</h1>
                <p>Generated on: ' . date('F d, Y H:i:s') . '</p>
            </div>

            <div class="info">
                <strong>Report Period:</strong> ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)) . '<br>
                <strong>Total Suppliers:</strong> ' . count($suppliers) . '
            </div>

            <div class="no-print" style="margin-bottom: 20px;">
                <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    Print as PDF
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Owner</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Rating</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Avg Order</th>
                        <th>Customers</th>
                        <th>Products</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($suppliers as $supplier) {
            echo '<tr>
                <td>' . htmlspecialchars($supplier['business_name']) . '</td>
                <td>' . htmlspecialchars($supplier['owner_name']) . '</td>
                <td>' . htmlspecialchars($supplier['city'] . ', ' . $supplier['province']) . '</td>
                <td>' . htmlspecialchars($supplier['contact_number']) . '</td>
                <td class="text-center">' . number_format($supplier['rating'] ?? 0, 1) . '</td>
                <td class="text-center">' . number_format($supplier['total_orders']) . '</td>
                <td class="text-right">₱' . number_format($supplier['total_revenue'] ?? 0, 2) . '</td>
                <td class="text-right">₱' . number_format($supplier['avg_order_value'] ?? 0, 2) . '</td>
                <td class="text-center">' . number_format($supplier['unique_customers']) . '</td>
                <td class="text-center">' . number_format($supplier['total_products']) . '</td>
                <td class="text-center">' . number_format($supplier['completion_rate'] ?? 0, 1) . '%</td>
            </tr>';
        }

        echo '</tbody></table>
        </body>
        </html>';
        exit;
        
    } else {
        // Generate CSV (Excel-compatible)
        $filename = 'supplier_performance_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Report header
        fputcsv($output, ['Supplier Performance Report']);
        fputcsv($output, ['Period: ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to))]);
        fputcsv($output, ['Generated: ' . date('F d, Y H:i:s')]);
        fputcsv($output, []); // Empty row

        // Column headers
        fputcsv($output, [
            'Supplier Name',
            'Owner',
            'Location',
            'Contact',
            'Rating',
            'Total Orders',
            'Revenue (₱)',
            'Avg Order (₱)',
            'Customers',
            'Products',
            'Completion Rate (%)'
        ]);

        // Data rows
        foreach ($suppliers as $supplier) {
            fputcsv($output, [
                $supplier['business_name'],
                $supplier['owner_name'],
                $supplier['city'] . ', ' . $supplier['province'],
                $supplier['contact_number'],
                number_format($supplier['rating'] ?? 0, 1),
                $supplier['total_orders'],
                number_format($supplier['total_revenue'] ?? 0, 2),
                number_format($supplier['avg_order_value'] ?? 0, 2),
                $supplier['unique_customers'],
                $supplier['total_products'],
                number_format($supplier['completion_rate'] ?? 0, 1)
            ]);
        }

        fclose($output);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]);
}
?>