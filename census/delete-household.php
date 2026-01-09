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
    $household_id = $input['household_id'] ?? 0;
    
    if (!$household_id) {
        echo json_encode(['success' => false, 'error' => 'No household ID provided']);
        exit();
    }
    
    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("SELECT family_identifier, household_head_id FROM households WHERE household_id = ?");
        $stmt->bind_param("i", $household_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $household = $result->fetch_assoc();
        
        if (!$household) {
            echo json_encode(['success' => false, 'error' => 'Household not found']);
            exit();
        }
        
        $family_identifier = $household['family_identifier'];
        $household_head_id = $household['household_head_id'];
        
        $stmt = $conn->prepare("DELETE FROM family_members WHERE family_identifier = ?");
        $stmt->bind_param("s", $family_identifier);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete family members");
        }
        
        $stmt = $conn->prepare("DELETE FROM household_heads WHERE id_number = ?");
        $stmt->bind_param("s", $household_head_id);
        $stmt->execute();
        
        
        $stmt = $conn->prepare("DELETE FROM households WHERE household_id = ?");
        $stmt->bind_param("i", $household_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete household");
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Household deleted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>