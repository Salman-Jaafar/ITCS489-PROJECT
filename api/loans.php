<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Debug-Auth');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($method === 'GET') {
        // Get all loans with details
        $stmt = $pdo->query('
            SELECT 
                l.LoanID as id,
                l.UserID as user_id,
                u.Name as member_name,
                l.CheckoutDate as checkout_date,
                l.DueDate as due_date,
                l.ReturnDate as return_date,
                l.FineAmount as fine_amount,
                GROUP_CONCAT(b.Title SEPARATOR ", ") as books,
                CASE 
                    WHEN l.ReturnDate IS NULL AND l.DueDate < CURDATE() THEN "Overdue"
                    WHEN l.ReturnDate IS NULL THEN "Active"
                    ELSE "Returned"
                END as status
            FROM Loan l
            JOIN User u ON l.UserID = u.UserID
            LEFT JOIN LoanItem li ON l.LoanID = li.LoanID
            LEFT JOIN Book b ON li.ISBN = b.ISBN
            GROUP BY l.LoanID
            ORDER BY l.CheckoutDate DESC
            LIMIT 100
        ');
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($loans);

    } elseif ($method === 'POST') {
        // Create new loan(s) - transactional
        $user_id = $input['user_id'] ?? $input['UserID'] ?? null;
        $books = $input['books'] ?? $input['ISBNs'] ?? []; // Array of ISBNs
        $due_days = (int)($input['due_days'] ?? 14);
        $due_date = $input['due_date'] ?? $input['DueDate'] ?? null;

        if (!$user_id || !is_array($books) || empty($books)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'user_id and books array required']);
            exit;
        }

        if (!$due_date) {
            $due_date = date('Y-m-d', strtotime("+$due_days days"));
        }

        $pdo->beginTransaction();
        try {
            // Create loan record
            $stmt = $pdo->prepare('
                INSERT INTO Loan (UserID, CheckoutDate, DueDate, FineAmount)
                VALUES (?, CURDATE(), ?, 0)
            ');
            $stmt->execute([$user_id, $due_date]);
            $loan_id = $pdo->lastInsertId();

            // Add loan items
            $stmt = $pdo->prepare('INSERT INTO LoanItem (LoanID, ISBN) VALUES (?, ?)');
            foreach ($books as $isbn) {
                $stmt->execute([$loan_id, $isbn]);
            }

            $pdo->commit();
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $loan_id, 'message' => 'Loan created']);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($method === 'PUT') {
        // Update loan (e.g., return it)
        $id = $input['id'] ?? $input['loanId'] ?? null;
        $return_date = $input['return_date'] ?? $input['ReturnDate'] ?? date('Y-m-d');

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Loan ID required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE Loan SET ReturnDate = ? WHERE LoanID = ?');
        $stmt->execute([$return_date, $id]);
        http_response_code(200);
        echo json_encode(['success' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>