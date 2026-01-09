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
        SELECT h.*, hh.full_name as head_name, hh.date_of_birth as head_dob, 
               hh.gender as head_gender, hh.marital_status, hh.education_level, 
               hh.occupation, hh.monthly_income
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

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=household_' . $household['family_identifier'] . '.csv');
    
    
    $output = fopen('php://output', 'w');

    
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    fputcsv($output, ['HOUSEHOLD INFORMATION']);
    fputcsv($output, ['Family Code', 'Head Name', 'Head ID', 'County', 'Sub-County', 'Ward', 'Phone', 'Total Members', 'Registration Date']);
    fputcsv($output, [
        $household['family_identifier'],
        $household['head_name'],
        $household['household_head_id'],
        $household['county'],
        $household['sub_county'],
        $household['ward'],
        $household['phone_number'],
        $household['total_members'],
        $household['registration_date']
    ]);
    
    
    fputcsv($output, []);
    
    fputcsv($output, ['FAMILY MEMBERS']);
    fputcsv($output, ['Name', 'ID Number', 'Date of Birth', 'Gender', 'Relationship', 'Age', 'Education Level', 'Occupation']);
    
    while ($member = $members->fetch_assoc()) {
        fputcsv($output, [
            $member['full_name'],
            $member['id_number'],
            $member['date_of_birth'],
            $member['gender'],
            $member['relationship_to_head'],
            $member['age_at_registration'],
            $member['education_level'],
            $member['occupation']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    die("Error exporting data: " . $e->getMessage());
}
?>