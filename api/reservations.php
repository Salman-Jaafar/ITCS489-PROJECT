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
$pdo = require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($method === 'GET') {
        // Get all reservations with details
        $stmt = $pdo->query('
            SELECT 
                r.ReservationID as id,
                r.UserID as user_id,
                u.Name as member_name,
                r.ReservationDate as reservation_date,
                r.ExpiryDate as expiry_date,
                r.Status as status,
                GROUP_CONCAT(DISTINCT b.Title SEPARATOR ", ") as books
            FROM Reservation r
            JOIN User u ON r.UserID = u.UserID
            LEFT JOIN ReservationItem ri ON r.ReservationID = ri.ReservationID
            LEFT JOIN Book b ON ri.ISBN = b.ISBN
            GROUP BY r.ReservationID
            ORDER BY r.ReservationDate DESC
            LIMIT 100
        ');
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($reservations);

    } elseif ($method === 'POST') {
        // Create new reservation
        $user_id = $input['user_id'] ?? null;
        $books = $input['books'] ?? []; // Array of ISBNs
        $expiry_days = (int)($input['expiry_days'] ?? 30);

        if (!$user_id || !is_array($books) || empty($books)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'user_id and books array required']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Create reservation
            $stmt = $pdo->prepare('
                INSERT INTO Reservation (UserID, ReservationDate, ExpiryDate, Status)
                VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), "Active")
            ');
            $stmt->execute([$user_id, $expiry_days]);
            $res_id = $pdo->lastInsertId();

            // Add reservation items
            $stmt = $pdo->prepare('INSERT INTO ReservationItem (ReservationID, ISBN) VALUES (?, ?)');
            foreach ($books as $isbn) {
                $stmt->execute([$res_id, $isbn]);
            }

            $pdo->commit();
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $res_id]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($method === 'PUT') {
        // Update reservation status
        $id = $input['id'] ?? null;
        $status = $input['status'] ?? null;

        if (!$id || !$status) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reservation ID and status required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE Reservation SET Status = ? WHERE ReservationID = ?');
        $stmt->execute([$status, $id]);
        http_response_code(200);
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        // Cancel reservation
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reservation ID required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE Reservation SET Status = "Cancelled" WHERE ReservationID = ?');
        $stmt->execute([$id]);
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
