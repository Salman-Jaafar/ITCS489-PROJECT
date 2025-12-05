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

try {
    // Admin-specific stats
    $librarians = $pdo->query("SELECT COUNT(*) FROM Employee WHERE Role='Librarian'")->fetchColumn();
    $admins = $pdo->query("SELECT COUNT(*) FROM Employee WHERE Role='Administrator'")->fetchColumn();
    $revenue = $pdo->query("SELECT IFNULL(SUM(AmountPaid), 0) FROM Payment")->fetchColumn();
    $total_books = $pdo->query("SELECT COUNT(*) FROM Book")->fetchColumn();
    $total_members = $pdo->query("SELECT COUNT(*) FROM User")->fetchColumn();
    $total_loans = $pdo->query("SELECT COUNT(*) FROM Loan")->fetchColumn();
    $overdue_loans = $pdo->query("SELECT COUNT(*) FROM Loan WHERE DueDate < CURDATE() AND ReturnDate IS NULL")->fetchColumn();

    http_response_code(200);
    echo json_encode([
        'totalLibrarians' => (int)$librarians,
        'totalAdministrators' => (int)$admins,
        'totalRevenue' => (float)$revenue,
        'totalBooks' => (int)$total_books,
        'totalMembers' => (int)$total_members,
        'totalLoans' => (int)$total_loans,
        'overdueLoans' => (int)$overdue_loans
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
