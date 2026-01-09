<?php
require 'db_connection.php';
require 'census-period-check.php';

echo "<h2>Census Status Test</h2>";
echo "<p>Current Date: " . date('Y-m-d') . "</p>";

$isActive = isCensusPeriodActive($conn);
$message = getCensusMessage($conn);

echo "<p>Census Active: " . ($isActive ? 'YES' : 'NO') . "</p>";
echo "<p>Message: " . $message . "</p>";


$sql = "SELECT setting_name, setting_value FROM system_settings 
        WHERE setting_name IN ('census_period_active', 'census_start_date', 'census_end_date')";
$result = $conn->query($sql);

echo "<h3>Database Values:</h3>";
while ($row = $result->fetch_assoc()) {
    echo "<p><strong>" . $row['setting_name'] . ":</strong> " . $row['setting_value'] . "</p>";
}
?>