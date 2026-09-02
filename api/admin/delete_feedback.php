<?php
require_once '../../config/config.php';
require_once '../../classes/Admin.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get the JSON data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['feedback_id']) || !is_numeric($input['feedback_id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid feedback ID']);
        exit;
    }
    
    $feedback_id = intval($input['feedback_id']);
    
    // Create admin instance
    $admin = new Admin($database);
    
    // Delete the feedback
    $result = $admin->deleteFeedback($feedback_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete feedback']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}