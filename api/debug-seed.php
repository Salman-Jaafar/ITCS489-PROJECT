<?php
header('Content-Type: application/json');

try {
    require __DIR__ . '/config.php';
    echo json_encode(['config_loaded' => true]);
    
    $pdo = require __DIR__ . '/db.php';
    echo json_encode(['db_connected' => true]);
    
    // Try simple insert
    $stmt = $pdo->prepare('INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, AccountStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $result = $stmt->execute([
        'Test',
        'User',
        'test' . time() . '@test.com',
        '1234567890',
        'Test Address',
        'hashedpassword',
        0,
        'User',
        3,
        'Active'
    ]);
    
    echo json_encode(['success' => $result]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine()]);
}
?>
