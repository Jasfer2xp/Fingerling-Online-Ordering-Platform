<?php
/**
 * API endpoint to set language preference
 */

session_start();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $language = $input['language'] ?? 'en';
    
    // Validate language
    if (!in_array($language, ['en', 'tl'])) {
        throw new Exception('Invalid language');
    }
    
    // Set language in session
    $_SESSION['preferred_language'] = $language;
    
    echo json_encode([
        'success' => true,
        'message' => 'Language preference updated',
        'language' => $language
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}