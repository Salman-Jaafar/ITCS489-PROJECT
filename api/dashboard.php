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
    // Stats
    $books = $pdo->query('SELECT COUNT(*) FROM Book')->fetchColumn();
    $members = $pdo->query("SELECT COUNT(*) FROM User WHERE AccountStatus='Active'")->fetchColumn();
    $due = $pdo->query("SELECT COUNT(*) FROM Loan WHERE DueDate = CURDATE() AND ReturnDate IS NULL")->fetchColumn();
    $fines = $pdo->query("SELECT IFNULL(SUM(Amount),0) FROM Fine WHERE Status='Unpaid'")->fetchColumn();
    $loans = $pdo->query("SELECT COUNT(*) FROM Loan WHERE ReturnDate IS NULL")->fetchColumn();

    $stats = [
        'totalBooks' => (int)$books,
        'activeMembers' => (int)$members,
        'dueToday' => (int)$due,
        'pendingFines' => (float)$fines,
        'activeLoans' => (int)$loans
    ];

    // Recent transactions
    $stmt = $pdo->query("
        SELECT 
            L.LoanID AS id, 
            U.Name AS member, 
            GROUP_CONCAT(B.Title SEPARATOR ', ') AS book,
            IF(L.ReturnDate IS NULL, 'Borrow', 'Return') AS type,
            L.CheckoutDate AS date, 
            L.DueDate AS dueDate,
            CASE WHEN L.ReturnDate IS NULL THEN 'Active' ELSE 'Returned' END AS status
        FROM Loan L
        LEFT JOIN User U ON L.UserID = U.UserID
        LEFT JOIN LoanItem LI ON LI.LoanID = L.LoanID
        LEFT JOIN Book B ON B.ISBN = LI.ISBN
        GROUP BY L.LoanID
        ORDER BY L.CheckoutDate DESC
        LIMIT 10
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Due books
    $stmt = $pdo->query("
        SELECT 
            B.Title AS title, 
            U.Name AS member, 
            U.UserID AS memberId,
            'Due Today' AS label
        FROM Loan L
        JOIN LoanItem LI ON LI.LoanID = L.LoanID
        JOIN Book B ON B.ISBN = LI.ISBN
        JOIN User U ON U.UserID = L.UserID
        WHERE L.DueDate = CURDATE() AND L.ReturnDate IS NULL
        LIMIT 10
    ");
    $dueBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent activities (payments + registrations)
    $stmt = $pdo->query("
        SELECT 
            'Fine Payment' AS title, 
            DATE(PaymentDate) AS time, 
            CONCAT('Payment of $', AmountPaid, ' processed') AS description
        FROM Payment
        ORDER BY PaymentDate DESC
        LIMIT 5
    ");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT 
            'New Member' AS title, 
            DATE(MembershipDate) AS time, 
            CONCAT(Name, ' joined the library') AS description
        FROM User
        WHERE MembershipDate IS NOT NULL
        ORDER BY MembershipDate DESC
        LIMIT 5
    ");
    $regs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activities = array_slice(array_merge($payments, $regs), 0, 10);

    http_response_code(200);
    echo json_encode([
        'stats' => $stats, 
        'transactions' => $transactions, 
        'dueBooks' => $dueBooks, 
        'activities' => $activities
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'details' => $e->getMessage()]);
}
?>
