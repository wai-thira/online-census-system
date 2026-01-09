<?php
session_start();
require 'db_connection.php';


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$household_id = $_GET['id'] ?? 0;

try {
   
    $household_stmt = $conn->prepare("
        SELECT h.*, hh.* 
        FROM households h 
        LEFT JOIN household_heads hh ON h.household_head_id = hh.id_number 
        WHERE h.household_id = ?
    ");
    $household_stmt->bind_param("i", $household_id);
    $household_stmt->execute();
    $household = $household_stmt->get_result()->fetch_assoc();
    

    $members_stmt = $conn->prepare("
        SELECT * FROM family_members 
        WHERE family_identifier = ? 
        ORDER BY relationship_to_head = 'Head' DESC, member_id
    ");
    $members_stmt->bind_param("s", $household['family_identifier']);
    $members_stmt->execute();
    $members = $members_stmt->get_result();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Household Details - <?php echo htmlspecialchars($household['family_identifier'] ?? 'N/A'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .back-btn { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 Household Details</h1>
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="section">
            <h2>Household Information</h2>
            <p><strong>Family Code:</strong> <?php echo htmlspecialchars($household['family_identifier'] ?? 'N/A'); ?></p>
            <p><strong>Head Name:</strong> <?php echo htmlspecialchars($household['full_name'] ?? 'N/A'); ?></p>
            <p><strong>Head ID:</strong> <?php echo htmlspecialchars($household['household_head_id'] ?? 'N/A'); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($household['county'] ?? 'N/A'); ?> County, <?php echo htmlspecialchars($household['sub_county'] ?? 'N/A'); ?> Sub-County, <?php echo htmlspecialchars($household['ward'] ?? 'N/A'); ?> Ward</p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($household['phone_number'] ?? 'N/A'); ?></p>
            <p><strong>Total Members:</strong> <?php echo htmlspecialchars($household['total_members'] ?? '0'); ?></p>
            <p><strong>Registration Date:</strong> <?php echo htmlspecialchars($household['registration_date'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Family Members</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>ID Number</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Relationship</th>
                        <th>Age</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($member = $members->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($member['date_of_birth']); ?></td>
                        <td><?php echo htmlspecialchars($member['gender']); ?></td>
                        <td><?php echo htmlspecialchars($member['relationship_to_head']); ?></td>
                        <td><?php echo htmlspecialchars($member['age_at_registration']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>