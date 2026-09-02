<?php
/**
 * Database Cleanup Script
 * Fingerling Online Ordering Platform
 */

// Include necessary files
require_once '../config/database.php';
require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
require_once '../includes/admin_footer.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize result message
$result_message = '';
$result_type = '';

// Handle cleanup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Count records before cleanup
        $tables_before = $pdo->query("SHOW TABLES")->rowCount();
        
        // Drop unused tables
        $drop_tables_sql = "
            DROP TABLE IF EXISTS customer_notes;
            DROP TABLE IF EXISTS cart_items;
        ";
        
        $pdo->exec($drop_tables_sql);
        
        // Clean up orphaned records
        $cleanup_sql = [
            "DELETE FROM feedback WHERE order_id NOT IN (SELECT id FROM orders)",
            "DELETE FROM notifications WHERE user_id NOT IN (SELECT id FROM users)",
            "DELETE FROM audit_log WHERE user_id NOT IN (SELECT id FROM users)",
            "DELETE FROM supplier_notes WHERE supplier_id NOT IN (SELECT id FROM suppliers)",
            "DELETE FROM order_tracking WHERE order_id NOT IN (SELECT id FROM orders)"
        ];
        
        $deleted_records = 0;
        foreach ($cleanup_sql as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $deleted_records += $stmt->rowCount();
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Get tables after cleanup
        $tables_after = $pdo->query("SHOW TABLES")->rowCount();
        
        $result_message = "Database cleanup completed successfully!
            <br>Tables removed: " . ($tables_before - $tables_after) . "
            <br>Orphaned records removed: $deleted_records";
        $result_type = "success";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $result_message = "Error during cleanup: " . $e->getMessage();
        $result_type = "error";
    }
}

// Get current database information
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$table_count = count($tables);
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Database Cleanup</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($result_message): ?>
                            <div class="alert alert-<?php echo $result_type; ?>">
                                <?php echo $result_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Current Database Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Total Tables:</strong> <?php echo $table_count; ?></p>
                                        <p><strong>Database Name:</strong> fingerling_marketplace</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Cleanup Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="form-group">
                                                <p>This action will:</p>
                                                <ul>
                                                    <li>Remove unused tables (customer_notes, cart_items)</li>
                                                    <li>Delete orphaned records</li>
                                                    <li>Optimize database tables</li>
                                                </ul>
                                            </div>
                                            <button type="submit" name="cleanup" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to clean up the database? This action cannot be undone.')">
                                                <i class="fas fa-broom"></i> Clean Up Database
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Current Tables</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Table Name</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($tables as $index => $table): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($table); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
?>