<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$pdo = require __DIR__ . '/db.php';

$uid = null;
if (isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
} elseif (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    $uid = (int)$_SESSION['user']['id'];
}

if (!$uid) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Loan L WHERE L.UserID = ? AND L.ReturnDate IS NULL');
    $stmt->execute([$uid]);
    $borrowed = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Loan WHERE UserID = ? AND DueDate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND ReturnDate IS NULL');
    $stmt->execute([$uid]);
    $dueSoon = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Reservation WHERE UserID = ? AND Status = "Active"');
    $stmt->execute([$uid]);
    $reserved = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT IFNULL(SUM(Amount),0) FROM Fine WHERE UserID = ? AND Status = "Unpaid"');
    $stmt->execute([$uid]);
    $fines = $stmt->fetchColumn();

    http_response_code(200);
    echo json_encode([
        'booksBorrowed' => (int)$borrowed,
        'dueSoon' => (int)$dueSoon,
        'reserved' => (int)$reserved,
        'pendingFines' => (float)$fines
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'details' => $e->getMessage()]);
}
?>
