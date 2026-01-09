<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require 'database.php';

try {
    $category = $_GET['category'] ?? null;
    
    $sql = "SELECT question, answer, category FROM faqs WHERE is_active = 1";
    $params = [];
    $types = "";
    
    if ($category && $category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $sql .= " ORDER BY category, id";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $faqs = [];
    while ($row = $result->fetch_assoc()) {
        $faqs[] = [
            'question' => htmlspecialchars($row['question']),
            'answer' => htmlspecialchars($row['answer']),
            'category' => htmlspecialchars($row['category'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $faqs,
        'count' => count($faqs)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load FAQs: ' . $e->getMessage()
    ]);
}

$conn->close();
?>