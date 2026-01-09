<?php

session_start();
require 'db_connection.php';

class NotificationSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function createAdminNotification($title, $message, $type = 'info') {
        $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (0, ?, ?, ?, 0, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $title, $message, $type);
        return $stmt->execute();
    }
    
    public function createUserNotification($user_id, $title, $message, $type = 'info') {
        $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $title, $message, $type);
        return $stmt->execute();
    }
    
    public function getUnreadCount($user_id = 0) {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['count'];
    }
    
    
    public function getNotifications($user_id = 0, $limit = 10) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
}


$notification_sql = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0, -- 0 for admin/system notifications
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','error') DEFAULT 'info',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (is_read),
    INDEX (created_at)
)";

$conn->query($notification_sql);
?>