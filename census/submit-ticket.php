<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require 'database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
   
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; 
    }
    
    $user_id = $input['user_id'] ?? 1; 
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');
    $priority = $input['priority'] ?? 'medium';
    
    
    if (empty($subject) || empty($message)) {
        echo json_encode([
            'success' => false,
            'error' => 'Subject and message are required'
        ]);
        exit;
    }
    
   
    $sql = "INSERT INTO support_tickets (user_id, subject, message, priority, status) VALUES (?, ?, ?, ?, 'open')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $subject, $message, $priority);
    
    if ($stmt->execute()) {
        $ticket_id = $stmt->insert_id;
        
        
        $notification_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (0, 'New Support Ticket', ?, 'info')";
        $notification_msg = "New support ticket #{$ticket_id}: {$subject}";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("s", $notification_msg);
        $notification_stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Support ticket submitted successfully',
            'ticket_id' => $ticket_id,
            'reference' => 'TICKET-' . str_pad($ticket_id, 6, '0', STR_PAD_LEFT)
        ]);
    } else {
        throw new Exception($conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to submit ticket: ' . $e->getMessage()
    ]);
}

$conn->close();
?>