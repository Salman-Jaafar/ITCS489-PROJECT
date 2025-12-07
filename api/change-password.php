<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = $data['userId'] ?? null;
$userType = $data['userType'] ?? null;
$currentPassword = $data['currentPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

if (!$userId || !$userType || !$currentPassword || !$newPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit;
}

try {
    if ($userType === 'user') {
        // Get user from User table
        $stmt = $pdo->prepare('SELECT Password FROM User WHERE UserID = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['Password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE User SET Password = ? WHERE UserID = ?');
        $stmt->execute([$newHash, $userId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        exit;

    } elseif ($userType === 'librarian' || $userType === 'admin') {
        // Get employee from Employee table
        $stmt = $pdo->prepare('SELECT Password FROM Employee WHERE EmployeeID = ? LIMIT 1');
        $stmt->execute([$userId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        // Verify current password
        if (!password_verify($currentPassword, $employee['Password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE Employee SET Password = ? WHERE EmployeeID = ?');
        $stmt->execute([$newHash, $userId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user type']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
