<?php
if ($_POST) {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $success_message = "Please enter a valid email address";
        $message_type = "error";
    } else {
        
        $success_message = "Password reset request received for: " . htmlspecialchars($email) . 
                          ". Please contact the system administrator at admin@npr-kenya.gov to reset your password.";
        $message_type = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Online Census</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>
    <div class="form-container">
        <form method="POST">
            <div class="form-header">
                <h1>Forgot Password</h1>
                <p class="form-subtitle">Enter your email to request password reset</p>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <h3><?php echo $message_type === 'success' ? 'Request Received!' : 'Error'; ?></h3>
                    <p><?php echo $success_message; ?></p>
                </div>
                
                <div class="form-actions">
                    <a href="login.html" class="btn btn-primary">Back to Login</a>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your registered email address">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Request Password Reset</button>
                </div>
            <?php endif; ?>
            
            <div class="form-footer">
                <p>Remember your password? <a href="login.html" class="back-link">Back to Login</a></p>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.innerHTML = '<span class="loading"></span> Processing...';
                    submitBtn.disabled = true;
                });
            }
        });
    </script>
   
</body>
</html>