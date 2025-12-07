<?php
header('Content-Type: application/json');

try {
    require __DIR__ . '/config.php';
    $pdo = require __DIR__ . '/db.php';

    $updates = [
        // Update User table
        'ALTER TABLE User MODIFY COLUMN Name VARCHAR(100)',  // Keep old column for now
        'ALTER TABLE User ADD COLUMN FirstName VARCHAR(50)',
        'ALTER TABLE User ADD COLUMN LastName VARCHAR(50)',
        'ALTER TABLE User ADD COLUMN Password VARCHAR(255)',
        'ALTER TABLE User ADD COLUMN IsStaff BOOLEAN DEFAULT 0',
        'ALTER TABLE User ADD COLUMN UserType ENUM("User","Doctor") DEFAULT "User"',
        'ALTER TABLE User ADD COLUMN MaxBooks INT DEFAULT 3',
        'ALTER TABLE User ADD COLUMN ProfilePhoto VARCHAR(255)',
        'ALTER TABLE User ADD COLUMN Address VARCHAR(100)',
        
        // Update Employee table
        'ALTER TABLE Employee ADD COLUMN FirstName VARCHAR(50)',
        'ALTER TABLE Employee ADD COLUMN LastName VARCHAR(50)',
        'ALTER TABLE Employee ADD COLUMN Password VARCHAR(255)',
        'ALTER TABLE Employee ADD COLUMN Phone VARCHAR(20)',
        'ALTER TABLE Employee ADD COLUMN Address VARCHAR(100)',
        'ALTER TABLE Employee ADD COLUMN MaxBooks INT DEFAULT 0',
        'ALTER TABLE Employee ADD COLUMN ProfilePhoto VARCHAR(255)',
        'ALTER TABLE Employee MODIFY COLUMN Email VARCHAR(100) NOT NULL',
    ];

    $completed = [];
    $skipped = [];
    
    foreach ($updates as $sql) {
        try {
            $pdo->exec($sql);
            $completed[] = $sql;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                $skipped[] = ['sql' => $sql, 'reason' => 'Column already exists'];
            } else if (strpos($e->getMessage(), '1054') !== false) {
                // Column not found - might be earlier version
                $skipped[] = ['sql' => $sql, 'reason' => 'Column not found'];
            } else {
                throw $e;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'completed' => count($completed),
        'skipped' => count($skipped),
        'message' => 'Database schema updated',
        'details' => [
            'completed' => $completed,
            'skipped' => $skipped
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
