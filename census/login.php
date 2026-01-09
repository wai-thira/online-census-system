<?php
session_start();
require 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT id, email, password_hash FROM users1 WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                
                header("Location: census-form.html");
                exit();
            } else {
                
                echo "<script>
                    alert('Invalid password! Please try again.');
                    window.location.href = 'login.html';
                </script>";
            }
        } else {
            
            echo "<script>
                alert('Email not found! Please register first.');
                window.location.href = 'register.html';
            </script>";
        }
        $stmt->close();
    } else {
        echo "Database error: " . $conn->error;
    }
} else {
    
    header("Location: login.html");
    exit();
}

$conn->close();
?>