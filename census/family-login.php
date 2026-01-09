<?php
session_start();
require 'db_connection.php';


if (isset($_SESSION['family_identifier'])) {
    header("Location: family-dashboard.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $family_identifier = trim($_POST['family_identifier']);
    $household_head_id = trim($_POST['household_head_id']);
    
    try {
        
        if (empty($family_identifier) || empty($household_head_id)) {
            throw new Exception("Please enter both Family Code and Household Head ID");
        }
        
        $sql = "SELECT h.*, hh.full_name, hh.id_number 
                FROM households h 
                JOIN household_heads hh ON h.household_id = hh.household_id 
                WHERE h.family_identifier = ? AND h.household_head_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $family_identifier, $household_head_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Invalid Family Code or Household Head ID. Please check your credentials.");
        }
        
        $household = $result->fetch_assoc();
        
        $_SESSION['family_identifier'] = $family_identifier;
        $_SESSION['household_head_id'] = $household_head_id;
        $_SESSION['household_id'] = $household['household_id'];
        $_SESSION['full_name'] = $household['full_name'];
        
        
        header("Location: family-dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Login - NPR Kenya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.9em;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #006400;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .info-box h4 {
            color: #006400;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            padding-left: 20px;
            color: #555;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #006400;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .login-help {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏠 Family Portal</h1>
            <p>National Population Registry - Kenya</p>
        </div>
        
        <div class="login-body">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                <div class="success-message">
                    <strong>Success!</strong> Your family has been registered successfully. You can now login to view your details.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="family-login.php">
                <div class="form-group">
                    <label for="family_identifier">Family Code *</label>
                    <input type="text" id="family_identifier" name="family_identifier" 
                           placeholder="Enter your Family Code (e.g., FAM1234567890)" 
                           value="<?php echo isset($_POST['family_identifier']) ? htmlspecialchars($_POST['family_identifier']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="household_head_id">Household Head ID *</label>
                    <input type="text" id="household_head_id" name="household_head_id" 
                           placeholder="Enter Household Head ID Number" 
                           value="<?php echo isset($_POST['household_head_id']) ? htmlspecialchars($_POST['household_head_id']) : ''; ?>" 
                           required>
                </div>
                
                <button type="submit" class="btn">🔐 Login to Family Portal</button>
            </form>
            
            <div class="info-box">
                <h4>📋 What you can do here:</h4>
                <ul>
                    <li>View complete household details</li>
                    <li>See all family members information</li>
                    <li>Check registration status</li>
                    <li>View location information</li>
                    <li>Access census registration summary</li>
                </ul>
            </div>
            
            <div class="login-help">
                <p>Don't have your Family Code? <br>Check your registration confirmation email or receipt.</p>
            </div>
            
            <div class="back-link">
                <a href="landing-page.html">← Return to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>