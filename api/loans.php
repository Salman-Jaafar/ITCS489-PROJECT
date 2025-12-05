<?php
session_start();
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $UserID = $data['UserID'] ?? null;
    $DueDate = $data['DueDate'] ?? null;
    $ISBNs = $data['ISBNs'] ?? null;
    if (!$UserID || !is_array($ISBNs) || count($ISBNs) === 0) { http_response_code(400); echo json_encode(['error'=>'UserID and ISBNs are required']); exit; }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO Loan (UserID, CheckoutDate, DueDate) VALUES (?, CURDATE(), ?)');
        $stmt->execute([$UserID, $DueDate]);
        $loanId = $pdo->lastInsertId();
        $stmtItem = $pdo->prepare('INSERT INTO LoanItem (LoanID, ISBN) VALUES (?, ?)');
        $stmtUpdate = $pdo->prepare('UPDATE Book SET Status = ? WHERE ISBN = ?');
        foreach ($ISBNs as $isbn) {
            $stmtItem->execute([$loanId, $isbn]);
            $stmtUpdate->execute(['Borrowed', $isbn]);
        }
        $pdo->commit();
        echo json_encode(['message'=>'Loan created','loanId'=>$loanId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500); echo json_encode(['error'=>'Failed to create loan','details'=>$e->getMessage()]);
    }
    exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
