if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the species name from the form
    $species_name = sanitize_input($_POST['species_name']);
    
    // Map species names to IDs (these should match your database)
    $species_map = [
        'Milkfish' => 1,
        'Catfish' => 2,
        'Tilapia' => 3,
        'Mudfish' => 4,
        'Carp' => 5,
        'Gourami' => 6,
        'Tilapiang Gloria' => 7,
        'Lapu-Lapu' => 8
    ];
    
    $species_id = $species_map[$species_name] ?? 0;
    
    $size_category = sanitize_input($_POST['size_category']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $price_per_piece = floatval($_POST['price_per_piece']);
    $minimum_order = intval($_POST['minimum_order']);
    
    // Validation
    if ($species_id <= 0) {
        $error = 'Please select a species.';
    } elseif (empty($size_category)) {
        $error = 'Please select a size category.';
    } elseif ($stock_quantity < 0) {
        $error = 'Stock quantity cannot be negative.';
    } elseif ($price_per_piece <= 0) {
        $error = 'Price must be greater than zero.';
    } elseif ($minimum_order <= 0) {
        $error = 'Minimum order must be at least 1.';
    } else {
        try {
            // Generate image path based on species name
            $image_path = null;
            if ($species_id > 0) {
                // Get species name from database
                $species_sql = "SELECT name FROM species WHERE id = ?";
                $species = $database->fetch($species_sql, [$species_id]);
                
                if ($species) {
                    // Convert species name to lowercase and replace spaces with hyphens
                    $image_filename = strtolower(str_replace([' ', '_'], '-', $species['name']));
                    // Add default extension (will be handled by the fallback mechanism)
                    $image_path = 'images/' . $image_filename . '.jpg';
                }
            }
            // Use the Supplier class method to add inventory item
            // Pass null for image_path since we're not uploading images manually
            $supplier->addInventoryItem($species_id, $size_category, $stock_quantity, $price_per_piece, $minimum_order, null);
            
            $_SESSION['success'] = 'Product added to inventory successfully!';
            redirect(base_url('supplier/inventory.php'));
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Add this section to handle image path when adding inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the species name from the form
    $species_name = sanitize_input($_POST['species_name']);
    
    // Map species names to IDs (these should match your database)
    $species_map = [
        'Milkfish' => 1,
        'Catfish' => 2,
        'Tilapia' => 3,
        'Mudfish' => 4,
        'Carp' => 5,
        'Gourami' => 6,
        'Tilapiang Gloria' => 7,
        'Lapu-Lapu' => 8
    ];
    
    $species_id = $species_map[$species_name] ?? 0;
    
    $size_category = sanitize_input($_POST['size_category']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $price_per_piece = floatval($_POST['price_per_piece']);
    $minimum_order = intval($_POST['minimum_order']);
    
    // Validation
    if ($species_id <= 0) {
        $error = 'Please select a species.';
    } elseif (empty($size_category)) {
        $error = 'Please select a size category.';
    } elseif ($stock_quantity < 0) {
        $error = 'Stock quantity cannot be negative.';
    } elseif ($price_per_piece <= 0) {
        $error = 'Price must be greater than zero.';
    } elseif ($minimum_order <= 0) {
        $error = 'Minimum order must be at least 1.';
    } else {
        try {
            // Use the Supplier class method to add inventory item
            // Pass null for image_path since we're not uploading images manually
            $supplier->addInventoryItem($species_id, $size_category, $stock_quantity, $price_per_piece, $minimum_order, null);
            
            $_SESSION['success'] = 'Product added to inventory successfully!';
            redirect(base_url('supplier/inventory.php'));
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}