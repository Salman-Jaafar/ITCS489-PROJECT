<?php
session_start();
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db.php';

try {
    // Stats
    $books = $pdo->query('SELECT COUNT(*) AS totalBooks FROM Book')->fetchColumn();
    $members = $pdo->query("SELECT COUNT(*) AS activeMembers FROM `User` WHERE AccountStatus='Active'")->fetchColumn();
    $due = $pdo->query("SELECT COUNT(*) AS dueToday FROM Loan WHERE DueDate = CURDATE() AND ReturnDate IS NULL")->fetchColumn();
    $fines = $pdo->query("SELECT IFNULL(SUM(Amount),0) AS pendingFines FROM Fine WHERE Status='Unpaid'")->fetchColumn();

    $stats = [
        'totalBooks' => (int)$books,
        'activeMembers' => (int)$members,
        'dueToday' => (int)$due,
        'pendingFines' => (float)$fines
    ];

    // Recent transactions
    $stmt = $pdo->query("SELECT L.LoanID AS id, U.Name AS member, B.Title AS book,
        IF(L.ReturnDate IS NULL, 'Borrow', 'Return') AS type,
        L.CheckoutDate AS date, L.DueDate AS dueDate,
        CASE WHEN L.ReturnDate IS NULL THEN 'Active' ELSE 'Returned' END AS status
      FROM Loan L
      LEFT JOIN `User` U ON L.UserID = U.UserID
      LEFT JOIN LoanItem LI ON LI.LoanID = L.LoanID
      LEFT JOIN Book B ON B.ISBN = LI.ISBN
      ORDER BY L.CheckoutDate DESC
      LIMIT 10");
    $transactions = $stmt->fetchAll();

    // Due books
    $stmt = $pdo->query("SELECT B.Title AS title, U.Name AS member, U.UserID AS memberId
      FROM Loan L
      JOIN LoanItem LI ON LI.LoanID = L.LoanID
      JOIN Book B ON B.ISBN = LI.ISBN
      JOIN `User` U ON U.UserID = L.UserID
      WHERE L.DueDate = CURDATE() AND L.ReturnDate IS NULL
      LIMIT 10");
    $dueBooks = $stmt->fetchAll();

    // Activities (payments + registrations)
    $stmt = $pdo->query("SELECT 'Fine Payment' AS title, PaymentDate AS time, CONCAT('Payment of ', AmountPaid, ' for Fine ID ', FineID) AS description
      FROM Payment
      ORDER BY PaymentDate DESC
      LIMIT 5");
    $payments = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT 'New Member Registration' AS title, MembershipDate AS time, CONCAT(Name, ' registered as a new member') AS description
      FROM `User`
      WHERE MembershipDate IS NOT NULL
      ORDER BY MembershipDate DESC
      LIMIT 5");
    $regs = $stmt->fetchAll();

    $activities = array_slice(array_merge($payments, $regs), 0, 10);

    echo json_encode(['stats' => $stats, 'transactions' => $transactions, 'dueBooks' => $dueBooks, 'activities' => $activities]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'details' => $e->getMessage()]);
}
