<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$household_id = $_GET['id'] ?? 0;

try {
    $stmt = $conn->prepare("SELECT * FROM households WHERE household_id = ?");
    $stmt->bind_param("i", $household_id);
    $stmt->execute();
    $household = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Household</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .back-btn { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
        .save-btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✏️ Edit Household</h1>
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <form action="update-household.php" method="POST">
            <input type="hidden" name="household_id" value="<?php echo $household_id; ?>">
            
            <div class="form-group">
                <label>Family Identifier:</label>
                <input type="text" name="family_identifier" value="<?php echo htmlspecialchars($household['family_identifier'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>County:</label>
                <input type="text" name="county" value="<?php echo htmlspecialchars($household['county'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Sub-County:</label>
                <input type="text" name="sub_county" value="<?php echo htmlspecialchars($household['sub_county'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Ward:</label>
                <input type="text" name="ward" value="<?php echo htmlspecialchars($household['ward'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($household['phone_number'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="save-btn">💾 Save Changes</button>
        </form>
    </div>
</body>
</html>