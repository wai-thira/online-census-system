<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $census_active = $_POST['census_period_active'] ?? '0';
    $start_date = $_POST['census_start_date'] ?? '';
    $end_date = $_POST['census_end_date'] ?? '';
    $message = $_POST['census_message'] ?? '';
    
    
    $settings = [
        'census_period_active' => $census_active,
        'census_start_date' => $start_date,
        'census_end_date' => $end_date,
        'census_message' => $message
    ];
    
    foreach ($settings as $name => $value) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $name, $value, $value);
        $stmt->execute();
    }
    
    $success = "Census settings updated successfully!";
}


$current_settings = [];
$result = $conn->query("SELECT setting_name, setting_value FROM system_settings 
                       WHERE setting_name IN ('census_period_active', 'census_start_date', 'census_end_date', 'census_message')");
while ($row = $result->fetch_assoc()) {
    $current_settings[$row['setting_name']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Census Period - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #006400; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #006400; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
        <h1>📅 Manage Census Period</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="census_period_active">Census Registration Status</label>
                <select name="census_period_active" id="census_period_active">
                    <option value="0" <?php echo ($current_settings['census_period_active'] ?? '0') == '0' ? 'selected' : ''; ?>>Closed</option>
                    <option value="1" <?php echo ($current_settings['census_period_active'] ?? '0') == '1' ? 'selected' : ''; ?>>Active</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="census_start_date">Census Start Date</label>
                <input type="date" name="census_start_date" id="census_start_date" 
                       value="<?php echo $current_settings['census_start_date'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="census_end_date">Census End Date</label>
                <input type="date" name="census_end_date" id="census_end_date" 
                       value="<?php echo $current_settings['census_end_date'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="census_message">Message when Census is Closed</label>
                <textarea name="census_message" id="census_message" rows="4" required><?php echo $current_settings['census_message'] ?? ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn">Save Settings</button>
        </form>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <h3>Current Status</h3>
            <p><strong>Registration Active:</strong> <?php echo (($current_settings['census_period_active'] ?? '0') == '1') ? 'Yes' : 'No'; ?></p>
            <p><strong>Start Date:</strong> <?php echo $current_settings['census_start_date'] ?? 'Not set'; ?></p>
            <p><strong>End Date:</strong> <?php echo $current_settings['census_end_date'] ?? 'Not set'; ?></p>
            <p><strong>Current Date:</strong> <?php echo date('Y-m-d'); ?></p>
        </div>
    </div>
</body>
</html>