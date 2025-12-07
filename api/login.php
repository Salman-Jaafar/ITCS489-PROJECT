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
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$userType = trim($data['userType'] ?? '');

if (empty($email) || empty($password) || empty($userType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email, password, and user type are required']);
    exit;
}

try {
    if ($userType === 'user') {
        // Check User table
        $stmt = $pdo->prepare('SELECT UserID, FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto FROM User WHERE Email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
            $userData = [
                'id' => $user['UserID'],
                'firstName' => $user['FirstName'],
                'lastName' => $user['LastName'],
                'email' => $user['Email'],
                'phone' => $user['Phone'],
                'address' => $user['Address'],
                'role' => 'User',
                'isStaff' => (bool)$user['IsStaff'],
                'userType' => $user['UserType'],
                'maxBooks' => $user['MaxBooks'],
                'profilePhoto' => $user['ProfilePhoto']
            ];
            
            $_SESSION['user'] = $userData;
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $userData
            ]);
            exit;
        }
    } elseif ($userType === 'librarian' || $userType === 'admin') {
        // Check Employee table
        $stmt = $pdo->prepare('SELECT EmployeeID, FirstName, LastName, Email, Phone, Address, Password, Role, MaxBooks, ProfilePhoto FROM Employee WHERE Email = ? AND Role = ? LIMIT 1');
        $stmt->execute([$email, ucfirst($userType)]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee && password_verify($password, $employee['Password'])) {
            $userData = [
                'id' => $employee['EmployeeID'],
                'firstName' => $employee['FirstName'],
                'lastName' => $employee['LastName'],
                'email' => $employee['Email'],
                'phone' => $employee['Phone'],
                'address' => $employee['Address'],
                'role' => $employee['Role'],
                'maxBooks' => $employee['MaxBooks'],
                'profilePhoto' => $employee['ProfilePhoto']
            ];
            
            $_SESSION['user'] = $userData;
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $userData
            ]);
            exit;
        }
    }

    // Invalid credentials
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login failed',
        'error' => $e->getMessage()
    ]);
}
?>
