<?php
require 'db_connection.php';

$current_date = date('Y-m-d');


$sql = "SELECT setting_name, setting_value FROM system_settings 
        WHERE setting_name IN ('census_start_date', 'census_end_date')";
$result = $conn->query($sql);

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

$start_date = $settings['census_start_date'] ?? '0000-00-00';
$end_date = $settings['census_end_date'] ?? '0000-00-00';


$is_active = ($current_date >= $start_date && $current_date <= $end_date);

$update_sql = "INSERT INTO system_settings (setting_name, setting_value) 
               VALUES ('census_period_active', ?) 
               ON DUPLICATE KEY UPDATE setting_value = ?";
$stmt = $conn->prepare($update_sql);
$active_value = $is_active ? '1' : '0';
$stmt->bind_param("ss", $active_value, $active_value);
$stmt->execute();

echo "Census period auto-updated. Active: " . ($is_active ? 'YES' : 'NO');
$conn->close();
?>