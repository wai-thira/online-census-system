<?php
session_start();
require 'db_connection.php';

header('Content-Type: application/json');

function isCensusPeriodActive($conn) {
    $sql = "SELECT setting_name, setting_value FROM system_settings 
            WHERE setting_name IN ('census_period_active', 'census_start_date', 'census_end_date', 'census_message')";
    $result = $conn->query($sql);
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    
    if (($settings['census_period_active'] ?? '0') == '1') {
        return [
            'active' => true,
            'start_date' => $settings['census_start_date'] ?? 'N/A',
            'end_date' => $settings['census_end_date'] ?? 'N/A',
            'message' => $settings['census_message'] ?? ''
        ];
    }
    
    $current_date = date('Y-m-d');
    $start_date = $settings['census_start_date'] ?? '0000-00-00';
    $end_date = $settings['census_end_date'] ?? '0000-00-00';
    
    $active = ($current_date >= $start_date && $current_date <= $end_date);
    
    return [
        'active' => $active,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'message' => $settings['census_message'] ?? 'Census registration is currently closed.'
    ];
}

echo json_encode(isCensusPeriodActive($conn));
$conn->close();
?>