<?php
session_start();
require 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    $member_id = $input['member_id'] ?? 0;
    
    if (!$member_id) {
        echo json_encode(['success' => false, 'error' => 'No member ID provided']);
        exit();
    }
    
    try {
        
        $check_stmt = $conn->prepare("SELECT member_id, family_identifier, relationship_to_head, is_adult, is_minor FROM family_members WHERE member_id = ?");
        $check_stmt->bind_param("i", $member_id);
        $check_stmt->execute();
        $member = $check_stmt->get_result()->fetch_assoc();
        
        if (!$member) {
            echo json_encode(['success' => false, 'error' => 'Member not found']);
            exit();
        }
        
        
        if ($member['relationship_to_head'] === 'Head') {
            echo json_encode(['success' => false, 'error' => 'Cannot delete household head. Delete the entire household instead.']);
            exit();
        }
        
        $family_identifier = $member['family_identifier'];
        $is_adult = $member['is_adult'];
        $is_minor = $member['is_minor'];
        
        $delete_stmt = $conn->prepare("DELETE FROM family_members WHERE member_id = ?");
        $delete_stmt->bind_param("i", $member_id);
        
        if ($delete_stmt->execute()) {
            
            if (!empty($family_identifier)) {
                $update_stmt = $conn->prepare("
                    UPDATE households 
                    SET total_members = total_members - 1,
                        adult_count = adult_count - ?,
                        minor_count = minor_count - ?
                    WHERE family_identifier = ?
                ");
                $adult_decrement = $is_adult ? 1 : 0;
                $minor_decrement = $is_minor ? 1 : 0;
                $update_stmt->bind_param("iis", $adult_decrement, $minor_decrement, $family_identifier);
                $update_stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => 'Family member deleted successfully']);
        } else {
            throw new Exception('Failed to delete member from database');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>