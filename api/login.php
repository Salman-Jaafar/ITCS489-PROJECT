<?php
session_start();
header('Content-Type: application/json');
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$email = $data['email'] ?? null;

// debug-only login: requires DEBUG_AUTH true or header
$debugHeader = (isset($_SERVER['HTTP_X_DEBUG_AUTH']) && $_SERVER['HTTP_X_DEBUG_AUTH'] === 'true');
if (!$email) { http_response_code(400); echo json_encode(['error'=>'email required']); exit; }

try {
    // Try Employee first
    $stmt = $pdo->prepare('SELECT * FROM Employee WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]); $emp = $stmt->fetch();
    if ($emp) {
        if (!$config['debug_auth'] && !$debugHeader) { http_response_code(403); echo json_encode(['error'=>'Debug auth disabled']); exit; }
        $_SESSION['user'] = ['id' => $emp['EmployeeID'], 'role' => $emp['Role'], 'name' => $emp['Name']];
        echo json_encode(['message'=>'Logged in as employee','user'=>$_SESSION['user']]); exit;
    }

    // Try User
    $stmt = $pdo->prepare('SELECT * FROM `User` WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]); $user = $stmt->fetch();
    if ($user) {
        if (!$config['debug_auth'] && !$debugHeader) { http_response_code(403); echo json_encode(['error'=>'Debug auth disabled']); exit; }
        $_SESSION['user'] = ['id' => $user['UserID'], 'role' => 'User', 'name' => $user['Name']];
        echo json_encode(['message'=>'Logged in as user','user'=>$_SESSION['user']]); exit;
    }

    http_response_code(404); echo json_encode(['error'=>'User not found']);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error'=>'Login failed','details'=>$e->getMessage()]);
}
