<?php

session_start();
require 'db_connection.php';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="census_data_' . date('Y-m-d') . '.xls"');


$county = $_GET['county'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM census_data WHERE 1=1";
$params = [];
$types = "";

if (!empty($county)) {
    $sql .= " AND county = ?";
    $params[] = $county;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR id_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql .= " ORDER BY submission_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


echo "Census Data Export - " . date('Y-m-d') . "\n\n";
echo "ID Number\tFull Name\tDate of Birth\tGender\tCounty\tSub-County\tWard\tPhone Number\tEducation Level\tOccupation\tHousehold Size\tSubmission Date\n";

while ($row = $result->fetch_assoc()) {
    echo $row['id_number'] . "\t";
    echo $row['full_name'] . "\t";
    echo $row['date_of_birth'] . "\t";
    echo $row['gender'] . "\t";
    echo $row['county'] . "\t";
    echo $row['sub_county'] . "\t";
    echo $row['ward'] . "\t";
    echo $row['phone_number'] . "\t";
    echo $row['education_level'] . "\t";
    echo $row['occupation'] . "\t";
    echo $row['household_size'] . "\t";
    echo $row['submission_date'] . "\n";
}

$stmt->close();
$conn->close();
exit;
?>