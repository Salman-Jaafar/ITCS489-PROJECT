<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Debug-Auth');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$pdo = require __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$email = $data['email'] ?? null;

// debug-only login: requires DEBUG_AUTH true or header
$debugHeader = (isset($_SERVER['HTTP_X_DEBUG_AUTH']) && $_SERVER['HTTP_X_DEBUG_AUTH'] === 'true');
if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'email required']);
    exit;
}

try {
    // Try Employee first
    $stmt = $pdo->prepare('SELECT EmployeeID, Name, Role, Email FROM Employee WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($emp) {
        if (!$config['debug_auth'] && !$debugHeader) {
            http_response_code(403);
            echo json_encode(['error' => 'Debug auth disabled']);
            exit;
        }
        $_SESSION['user'] = ['id' => $emp['EmployeeID'], 'role' => $emp['Role'], 'name' => $emp['Name']];
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Logged in as employee', 'user' => $_SESSION['user']]);
        exit;
    }

    // Try User
    $stmt = $pdo->prepare('SELECT UserID, Name, Email FROM User WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!$config['debug_auth'] && !$debugHeader) {
            http_response_code(403);
            echo json_encode(['error' => 'Debug auth disabled']);
            exit;
        }
        $_SESSION['user'] = ['id' => $user['UserID'], 'role' => 'User', 'name' => $user['Name']];
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Logged in as user', 'user' => $_SESSION['user']]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Login failed', 'details' => $e->getMessage()]);
}
?>
