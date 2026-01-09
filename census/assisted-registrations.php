<?php
session_start();
require 'db_connection.php';

$sql = "SELECT 
    COUNT(*) as total_registrations,
    SUM(CASE WHEN registration_type = 'assisted' THEN 1 ELSE 0 END) as assisted_count,
    helper_location,
    COUNT(*) as location_count
FROM households 
WHERE registration_type = 'assisted' 
GROUP BY helper_location 
ORDER BY location_count DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assisted Registration Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .report-container { max-width: 800px; margin: 0 auto; }
        .stat-box { background: #f8f9fa; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .location-item { background: #e7f3ff; padding: 10px; margin: 5px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="report-container">
        <h2>🌍 Assisted Registration Report</h2>
        
        <div class="stat-box">
            <h3>Registration Statistics</h3>
            <?php
            $total_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN registration_type = 'assisted' THEN 1 ELSE 0 END) as assisted
                FROM households";
            $total_result = $conn->query($total_sql);
            $total_data = $total_result->fetch_assoc();
            
            $assisted_percentage = $total_data['total'] > 0 ? 
                round(($total_data['assisted'] / $total_data['total']) * 100, 2) : 0;
            ?>
            
            <p><strong>Total Registrations:</strong> <?php echo $total_data['total']; ?></p>
            <p><strong>Assisted Registrations:</strong> <?php echo $total_data['assisted']; ?></p>
            <p><strong>Assisted Percentage:</strong> <?php echo $assisted_percentage; ?>%</p>
        </div>

        <div class="stat-box">
            <h3>Assistance Locations</h3>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<div class='location-item'>";
                    echo "<strong>" . ucfirst(str_replace('_', ' ', $row['helper_location'])) . ":</strong> ";
                    echo $row['location_count'] . " registrations";
                    echo "</div>";
                }
            } else {
                echo "<p>No assisted registrations yet.</p>";
            }
            ?>
        </div>
        
        <a href="landing-page.html" class="btn">← Back to Home</a>
    </div>
</body>
</html>