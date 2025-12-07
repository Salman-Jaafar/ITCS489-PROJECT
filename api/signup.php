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

$firstName = trim($data['firstName'] ?? '');
$lastName = trim($data['lastName'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$password = $data['password'] ?? '';
$isDoctor = (bool)($data['isDoctor'] ?? false);

// Validation
$errors = [];

if (empty($firstName)) {
    $errors[] = 'First name is required';
} elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
    $errors[] = 'First name must contain only letters and spaces';
}

if (empty($lastName)) {
    $errors[] = 'Last name is required';
} elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
    $errors[] = 'Last name must contain only letters and spaces';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($address)) {
    $errors[] = 'Address is required';
} elseif (strlen($address) > 100) {
    $errors[] = 'Address must be less than 100 characters';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $errors[0]]);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT UserID FROM User WHERE Email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    // Determine max books based on doctor status
    $maxBooks = $isDoctor ? 6 : 3;
    $userType = $isDoctor ? 'Doctor' : 'User';

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user
    $stmt = $pdo->prepare('INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto, AccountStatus, MembershipDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $address,
        $hashedPassword,
        $isDoctor ? 1 : 0,
        $userType,
        $maxBooks,
        null,
        'Active'
    ]);

    $userId = $pdo->lastInsertId();

    // Return user info
    $user = [
        'id' => $userId,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'role' => 'User',
        'isDoctor' => $isDoctor,
        'maxBooks' => $maxBooks
    ];

    $_SESSION['user'] = $user;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'user' => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed',
        'error' => $e->getMessage()
    ]);
}
?>
