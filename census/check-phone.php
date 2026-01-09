<?php
session_start();
require 'db_connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone_number = trim($_POST['phone_number']);
    
    if (empty($phone_number) || !preg_match('/^[0-9]{10}$/', $phone_number)) {
        echo json_encode(['available' => true, 'message' => 'Invalid phone number format']);
        exit;
    }
    
    try {
        
        $check_sql = "SELECT household_id FROM households WHERE phone_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $phone_number);
        
        if (!$check_stmt->execute()) {
            throw new Exception("Failed to execute query: " . $check_stmt->error);
        }
        
        $result = $check_stmt->get_result();
        $is_available = $result->num_rows === 0;
        
        $check_stmt->close();
        
        echo json_encode([
            'available' => $is_available,
            'message' => $is_available ? 'Phone number available' : 'Phone number already registered'
        ]);
        
    } catch (Exception $e) {
        error_log("Phone check error: " . $e->getMessage());
        echo json_encode(['available' => true, 'message' => 'Error checking phone number']);
    }
} else {
    echo json_encode(['available' => true, 'message' => 'Invalid request method']);
}

$conn->close();
?>