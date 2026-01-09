<?php
session_start();
require 'db_connection.php';


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$member_id = $_GET['id'] ?? 0;

try {
    $stmt = $conn->prepare("SELECT * FROM family_members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Member Details - <?php echo htmlspecialchars($member['full_name'] ?? 'N/A'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .back-btn { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Family Member Details</h1>
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="section">
            <h2>Personal Information</h2>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($member['full_name'] ?? 'N/A'); ?></p>
            <p><strong>ID Number:</strong> <?php echo htmlspecialchars($member['id_number'] ?? 'N/A'); ?></p>
            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($member['date_of_birth'] ?? 'N/A'); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($member['age_at_registration'] ?? 'N/A'); ?> years</p>
        </div>

        <div class="section">
            <h2>Family Information</h2>
            <p><strong>Family Code:</strong> <?php echo htmlspecialchars($member['family_identifier'] ?? 'N/A'); ?></p>
            <p><strong>Relationship to Head:</strong> <?php echo htmlspecialchars($member['relationship_to_head'] ?? 'N/A'); ?></p>
            <p><strong>Education Level:</strong> <?php echo htmlspecialchars($member['education_level'] ?? 'N/A'); ?></p>
            <p><strong>Occupation:</strong> <?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Registration Details</h2>
            <p><strong>Registration Date:</strong> <?php echo htmlspecialchars($member['registration_date'] ?? 'N/A'); ?></p>
            <p><strong>Is Minor:</strong> <?php echo ($member['is_minor'] ?? 0) ? 'Yes' : 'No'; ?></p>
            <p><strong>Is Adult:</strong> <?php echo ($member['is_adult'] ?? 0) ? 'Yes' : 'No'; ?></p>
            <p><strong>Can Register Independently:</strong> <?php echo ($member['can_register_independently'] ?? 0) ? 'Yes' : 'No'; ?></p>
        </div>
    </div>
</body>
</html>