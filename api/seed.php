<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require __DIR__ . '/config.php';
    $pdo = require __DIR__ . '/db.php';

    // Check if John already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM User WHERE Email = ?');
    $stmt->execute(['john@gmail.com']);
    $result = $stmt->fetch();
    
    if ($result && $result['cnt'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Seed users already exist']);
        exit;
    }

    $inserted = 0;

    // Insert User 1: John Doe
    try {
        $stmt = $pdo->prepare('INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto, AccountStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'John', 'Doe', 'john@gmail.com', '1234567890', '43A',
            '$2y$10$BvwrU1MHHlRb8LU6.MlqsuLxBiaxGIWAk1QHGqWtKwxXDiUqAWCm2',
            1, 'Doctor', 6, null, 'Active'
        ]);
        $johnId = $pdo->lastInsertId();
        $inserted++;
    } catch (Exception $e) {
        throw new Exception('Failed to insert John Doe: ' . $e->getMessage());
    }

    // Insert User 2: James Bond
    try {
        $stmt = $pdo->prepare('INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto, AccountStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'James', 'Bond', 'james@gmail.com', '0987654321', '43A',
            '$2y$10$BvwrU1MHHlRb8LU6.MlqsuLxBiaxGIWAk1QHGqWtKwxXDiUqAWCm2',
            0, 'User', 3, null, 'Active'
        ]);
        $inserted++;
    } catch (Exception $e) {
        throw new Exception('Failed to insert James Bond: ' . $e->getMessage());
    }

    // Insert Employee 1: Al-Sayed Mustafa (Librarian)
    try {
        $stmt = $pdo->prepare('INSERT INTO Employee (FirstName, LastName, Email, Phone, Address, Password, Position, AccessLevel, Role, MaxBooks, ProfilePhoto, AccountStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'Al-Sayed', 'Al-Haddar', 'sayed@gmail.com', '1111111111', '245-B',
            '$2y$10$8Dt4FqVpMXXhIVj7u6CPkuMSIxIdVqgqNCDMI8F6rNb7IvCuQPkWi',
            'Librarian', 2, 'Librarian', 0, null, 'Active'
        ]);
        $inserted++;
    } catch (Exception $e) {
        throw new Exception('Failed to insert librarian: ' . $e->getMessage());
    }

    // Insert Employee 2: Baqir (Administrator)
    try {
        $stmt = $pdo->prepare('INSERT INTO Employee (FirstName, LastName, Email, Phone, Address, Password, Position, AccessLevel, Role, MaxBooks, ProfilePhoto, AccountStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'Baqir', 'Al-Haddar', 'baqir@gmail.com', '2222222222', '4B',
            '$2y$10$8Dt4FqVpMXXhIVj7u6CPkuMSIxIdVqgqNCDMI8F6rNb7IvCuQPkWi',
            'Administrator', 3, 'Administrator', 0, null, 'Active'
        ]);
        $inserted++;
    } catch (Exception $e) {
        throw new Exception('Failed to insert admin: ' . $e->getMessage());
    }

    // Create reservation for John Doe
    try {
        if ($johnId) {
            $stmt = $pdo->prepare('INSERT INTO Reservation (UserID, Status) VALUES (?, ?)');
            $stmt->execute([$johnId, 'Active']);
            $reservationId = $pdo->lastInsertId();

            // Get first book ISBN
            $stmt = $pdo->query('SELECT ISBN FROM Book LIMIT 1');
            $book = $stmt->fetch();
            
            if ($book && $reservationId) {
                $stmt = $pdo->prepare('INSERT INTO ReservationItem (ReservationID, ISBN) VALUES (?, ?)');
                $stmt->execute([$reservationId, $book['ISBN']]);
            }
        }
    } catch (Exception $e) {
        // Reservation insert is not critical
        error_log('Warning: Failed to create reservation: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Seed data inserted successfully',
        'inserted' => $inserted,
        'users' => [
            'john@gmail.com (Doctor/Staff, 6 books max) - Password: 12345678',
            'james@gmail.com (Regular User, 3 books max) - Password: 12345678',
            'sayed@gmail.com (Librarian, unlimited) - Password: abcdefgh',
            'baqir@gmail.com (Administrator, unlimited) - Password: abcdefgh'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to insert seed data',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
