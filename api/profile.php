<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'config.php';
$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user profile
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $user = $_SESSION['user'];
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $user = $_SESSION['user'];
    $userId = $user['id'];
    $isEmployee = isset($user['role']) && in_array($user['role'], ['Librarian', 'Administrator']);
    
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $profilePhoto = null;

    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        $tmpFile = $_FILES['profilePhoto']['tmp_name'];
        $fileName = $_FILES['profilePhoto']['name'];
        $fileType = $_FILES['profilePhoto']['type'];
        $fileSize = $_FILES['profilePhoto']['size'];

        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($fileType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF allowed']);
            exit;
        }

        if ($fileSize > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
            exit;
        }

        // Generate unique filename
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = $userId . '_' . time() . '.' . $ext;
        $newFilePath = $uploadDir . $newFileName;

        if (move_uploaded_file($tmpFile, $newFilePath)) {
            $profilePhoto = 'profiles/' . $newFileName;

            // Update database
            try {
                if ($isEmployee) {
                    $stmt = $pdo->prepare('UPDATE Employee SET ProfilePhoto = ? WHERE EmployeeID = ?');
                    $stmt->execute([$profilePhoto, $userId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE User SET ProfilePhoto = ? WHERE UserID = ?');
                    $stmt->execute([$profilePhoto, $userId]);
                }

                // Update session
                $_SESSION['user']['profilePhoto'] = $profilePhoto;

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile photo updated successfully',
                    'profilePhoto' => $profilePhoto
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update profile',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
