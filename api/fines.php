<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Debug-Auth');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$pdo = require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($method === 'GET') {
        // Get all fines
        $stmt = $pdo->query('
            SELECT 
                f.FineID as id,
                f.UserID as user_id,
                u.Name as member_name,
                f.LoanID as loan_id,
                f.IssueDate as issue_date,
                f.Amount as amount,
                f.Status as status,
                f.Description as description,
                GROUP_CONCAT(DISTINCT b.Title SEPARATOR ", ") as books
            FROM Fine f
            JOIN User u ON f.UserID = u.UserID
            LEFT JOIN Loan l ON f.LoanID = l.LoanID
            LEFT JOIN LoanItem li ON l.LoanID = li.LoanID
            LEFT JOIN Book b ON li.ISBN = b.ISBN
            GROUP BY f.FineID
            ORDER BY f.IssueDate DESC
            LIMIT 100
        ');
        $fines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($fines);

    } elseif ($method === 'POST') {
        $action = $input['action'] ?? null;

        if ($action === 'pay') {
            // Process payment for selected fines
            $fine_ids = $input['fine_ids'] ?? [];
            $payment_method = $input['payment_method'] ?? 'Cash';

            if (!is_array($fine_ids) || empty($fine_ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'fine_ids array required']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO Payment (FineID, PaymentDate, AmountPaid, PaymentMethod)
                    SELECT FineID, CURDATE(), Amount, ? FROM Fine WHERE FineID = ?
                ');

                foreach ($fine_ids as $fine_id) {
                    $stmt->execute([$payment_method, $fine_id]);
                    
                    // Mark fine as paid
                    $updateStmt = $pdo->prepare('UPDATE Fine SET Status = "Paid" WHERE FineID = ?');
                    $updateStmt->execute([$fine_id]);
                }

                $pdo->commit();
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
