<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Admin.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get parameters
$type = $_GET['type'] ?? 'sales';
$format = strtolower($_GET['format'] ?? 'pdf');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$supplier_id = $_GET['supplier_id'] ?? null;

try {
    // Get report data based on type
    switch ($type) {
        case 'sales':
            $data = getSalesReportData($database, $start_date, $end_date, $supplier_id);
            $title = 'Sales Report';
            break;
        case 'suppliers':
            $data = getSuppliersReportData($database, $start_date, $end_date);
            $title = 'Suppliers Report';
            break;
        case 'orders':
            $data = getOrdersReportData($database, $start_date, $end_date, $supplier_id);
            $title = 'Orders Report';
            break;
        default:
            throw new Exception('Invalid report type');
    }
    
    if (empty($data)) {
        // Return error for no data
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No data available for this date range.'
        ]);
        exit;
    }
    
    // Generate report based on format
    if ($format === 'pdf') {
        generatePDFReport($data, $title, $start_date, $end_date, $type);
    } elseif ($format === 'excel' || $format === 'csv') {
        generateExcelReport($data, $title, $start_date, $end_date, $type);
    } else {
        throw new Exception('Invalid format');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

/**
 * Get sales report data
 */
function getSalesReportData($database, $start_date, $end_date, $supplier_id = null) {
    $sql = "
        SELECT
            o.id as order_id,
            o.order_number,
            o.created_at,
            o.total_amount,
            o.status,
            s.business_name as supplier_name,
            c.first_name,
            c.last_name,
            u.email as customer_email,
            c.contact_number as customer_phone,
            COUNT(oi.id) as total_items
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        JOIN customers c ON o.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
    ";

    $params = [$start_date, $end_date];

    if ($supplier_id) {
        $sql .= " AND o.supplier_id = ?";
        $params[] = $supplier_id;
    }

    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

    return $database->fetchAll($sql, $params);
}

/**
 * Get suppliers report data
 */
function getSuppliersReportData($database, $start_date, $end_date) {
    $sql = "
        SELECT
            s.id,
            s.business_name,
            s.owner_name,
            s.contact_number,
            s.email,
            s.supplier_status,
            s.created_at,
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COUNT(DISTINCT p.id) as total_products
        FROM suppliers s
        LEFT JOIN orders o ON s.id = o.supplier_id
            AND DATE(o.created_at) BETWEEN ? AND ?
        LEFT JOIN products p ON s.id = p.supplier_id
        GROUP BY s.id
        ORDER BY total_revenue DESC
    ";

    return $database->fetchAll($sql, [$start_date, $end_date]);
}

/**
 * Get orders report data
 */
function getOrdersReportData($database, $start_date, $end_date, $supplier_id = null) {
    $sql = "
        SELECT
            o.id,
            o.order_number,
            o.created_at,
            o.total_amount,
            o.status,
            s.business_name as supplier_name,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            u.email as customer_email,
            c.contact_number as customer_phone,
            COUNT(oi.id) as items_count,
            GROUP_CONCAT(CONCAT(sp.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        JOIN customers c ON o.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN inventory i ON oi.inventory_id = i.id
        LEFT JOIN species sp ON i.species_id = sp.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
    ";

    $params = [$start_date, $end_date];

    if ($supplier_id) {
        $sql .= " AND o.supplier_id = ?";
        $params[] = $supplier_id;
    }

    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

    return $database->fetchAll($sql, $params);
}

/**
 * Generate PDF report using basic HTML to PDF
 */
function generatePDFReport($data, $title, $start_date, $end_date, $type) {
    // For now, we'll use a simple HTML to PDF approach
    // In production, you'd want to use a proper PDF library like TCPDF or FPDF
    
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.pdf';
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate HTML content
    $html = generateReportHTML($data, $title, $start_date, $end_date, $type);
    
    // Simple HTML to PDF conversion (basic approach)
    // Note: This is a simplified version. For production, use proper PDF libraries
    echo $html;
}

/**
 * Generate Excel report using CSV format
 */
function generateExcelReport($data, $title, $start_date, $end_date, $type) {
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add title and date range
    fputcsv($output, [$title]);
    fputcsv($output, ['Date Range: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, []); // Empty row
    
    if (!empty($data)) {
        // Add headers
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

/**
 * Generate HTML content for reports
 */
function generateReportHTML($data, $title, $start_date, $end_date, $type) {
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>$title</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .date-range { color: #666; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .total-row { background-color: #f9f9f9; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>$title</h1>
            <p class='date-range'>Date Range: $start_date to $end_date</p>
            <p class='date-range'>Generated on: " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <table>
    ";
    
    if (!empty($data)) {
        // Add table headers
        $html .= "<tr>";
        foreach (array_keys($data[0]) as $header) {
            $html .= "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
        }
        $html .= "</tr>";
        
        // Add data rows
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            $html .= "</tr>";
        }
    } else {
        $html .= "<tr><td colspan='100%' style='text-align: center;'>No data available for this date range.</td></tr>";
    }
    
    $html .= "
        </table>
    </body>
    </html>
    ";
    
    return $html;
}
?>
