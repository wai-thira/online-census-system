<?php

session_start();
require 'db_connection.php';


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

$sql .= " ORDER BY submission_date DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .summary { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NATIONAL POPULATION REGISTRY - KENYA</h1>
        <h2>Census Data Report</h2>
        <p>Generated: ' . date('Y-m-d H:i:s') . '</p>
    </div>
    
    <div class="summary">
        <p><strong>Total Records:</strong> ' . $result->num_rows . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID Number</th>
                <th>Full Name</th>
                <th>County</th>
                <th>Sub-County</th>
                <th>Gender</th>
                <th>Submission Date</th>
            </tr>
        </thead>
        <tbody>';

while ($row = $result->fetch_assoc()) {
    $html .= '
            <tr>
                <td>' . htmlspecialchars($row['id_number']) . '</td>
                <td>' . htmlspecialchars($row['full_name']) . '</td>
                <td>' . htmlspecialchars($row['county']) . '</td>
                <td>' . htmlspecialchars($row['sub_county']) . '</td>
                <td>' . htmlspecialchars($row['gender']) . '</td>
                <td>' . $row['submission_date'] . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';


header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="census_report_' . date('Y-m-d') . '.html"');
echo $html;

$stmt->close();
$conn->close();
exit;
?>