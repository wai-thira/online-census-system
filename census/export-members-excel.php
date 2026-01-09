<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

try {
    $result = $conn->query("
        SELECT fm.family_identifier, fm.full_name, fm.id_number, fm.date_of_birth, 
               fm.gender, fm.relationship_to_head, fm.age_at_registration, 
               fm.education_level, fm.occupation, fm.registration_date,
               h.county, h.sub_county
        FROM family_members fm
        LEFT JOIN households h ON fm.family_identifier = h.family_identifier
        ORDER BY fm.registration_date DESC
    ");

    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=family_members_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    fputcsv($output, [
        'Family Code',
        'Full Name', 
        'ID Number', 
        'Date of Birth', 
        'Gender', 
        'Relationship', 
        'Age',
        'Education Level',
        'Occupation',
        'County',
        'Sub-County',
        'Registration Date'
    ]);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['family_identifier'],
            $row['full_name'],
            $row['id_number'],
            $row['date_of_birth'],
            $row['gender'],
            $row['relationship_to_head'],
            $row['age_at_registration'],
            $row['education_level'],
            $row['occupation'],
            $row['county'],
            $row['sub_county'],
            $row['registration_date']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    die("Error exporting data: " . $e->getMessage());
}
?>

