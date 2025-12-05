<?php
session_start();
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db.php';

try {
    $librarians = $pdo->query("SELECT COUNT(*) AS totalLibrarians FROM Employee WHERE Role IN ('Librarian','Administrator')")->fetchColumn();
    $revenue = $pdo->query("SELECT IFNULL(SUM(AmountPaid),0) AS totalRevenue FROM Payment")->fetchColumn();
    echo json_encode(['totalLibrarians' => (int)$librarians, 'totalRevenue' => (float)$revenue]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'details' => $e->getMessage()]);
}
