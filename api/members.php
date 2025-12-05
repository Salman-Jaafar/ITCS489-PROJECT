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
        // Get all members
        $stmt = $pdo->query('
            SELECT UserID as id, Name as name, Email as email, Phone as phone, 
                   City as city, AccountStatus as status, MembershipDate as member_since
            FROM User
            ORDER BY UserID DESC
            LIMIT 100
        ');
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($members);

    } elseif ($method === 'POST') {
        // Create new member
        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $phone = $input['phone'] ?? null;
        $city = $input['city'] ?? null;

        if (!$name || !$email) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and Email are required']);
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO User (Name, Email, Phone, City, AccountStatus, MembershipDate)
            VALUES (?, ?, ?, ?, ?, CURDATE())
        ');
        $stmt->execute([$name, $email, $phone, $city, 'Active']);
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($method === 'PUT') {
        // Update member
        $id = $input['id'] ?? null;
        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $phone = $input['phone'] ?? null;
        $city = $input['city'] ?? null;
        $status = $input['status'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Member ID required']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE User 
            SET Name = COALESCE(?, Name),
                Email = COALESCE(?, Email),
                Phone = COALESCE(?, Phone),
                City = COALESCE(?, City),
                AccountStatus = COALESCE(?, AccountStatus)
            WHERE UserID = ?
        ');
        $stmt->execute([$name, $email, $phone, $city, $status, $id]);
        http_response_code(200);
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        // Delete member
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Member ID required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM User WHERE UserID = ?');
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
