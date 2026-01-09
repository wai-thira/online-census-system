<?php
function isCensusPeriodActive($conn) {
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = 'census_period_active'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $manual_override = $row['setting_value'];
        
        if ($manual_override == '1') {
            return true;
        }
        
        elseif ($manual_override == '0') {
            return false;
        }
    }
    
    $start_sql = "SELECT setting_value FROM system_settings WHERE setting_name = 'census_start_date'";
    $end_sql = "SELECT setting_value FROM system_settings WHERE setting_name = 'census_end_date'";
    
    $start_result = $conn->query($start_sql);
    $end_result = $conn->query($end_sql);
    
    $start_date = '0000-00-00';
    $end_date = '0000-00-00';
    
    if ($start_result && $start_result->num_rows > 0) {
        $start_row = $start_result->fetch_assoc();
        $start_date = $start_row['setting_value'];
    }
    
    if ($end_result && $end_result->num_rows > 0) {
        $end_row = $end_result->fetch_assoc();
        $end_date = $end_row['setting_value'];
    }
    
    $current_date = date('Y-m-d');
    
    
    error_log("Census Check - Current: $current_date, Start: $start_date, End: $end_date");
    
    return ($current_date >= $start_date && $current_date <= $end_date);
}

function getCensusMessage($conn) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = 'census_message'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return "Census registration is currently closed. Please wait for the next census period.";
}
?>