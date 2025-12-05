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
$pdo = require __DIR__ . '/db.php';
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    // Get one or many books. Accept ?isbn= or return list
    try {
        if (isset($_GET['isbn'])) {
            $isbn = $_GET['isbn'];
            $stmt = $pdo->prepare('SELECT ISBN as isbn, Title as title, Author as author, PublicationYear as year, Genre as category, Status as status, ShelfLocation as shelf_location FROM Book WHERE ISBN = ? LIMIT 1');
            $stmt->execute([$isbn]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$book) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Book not found']); exit; }
            http_response_code(200); echo json_encode($book);
            exit;
        }

        // list
        $stmt = $pdo->query('SELECT ISBN as isbn, Title as title, Author as author, Genre as category, Status as status, ShelfLocation as shelf_location FROM Book ORDER BY Title LIMIT 200');
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode(['books' => $books]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Create new book
    // Basic validation
    $title = trim($input['title'] ?? '');
    $author = trim($input['author'] ?? '');
    $isbn = trim($input['isbn'] ?? ($input['ISBN'] ?? ''));
    $category = trim($input['category'] ?? ($input['genre'] ?? ''));
    $shelf_location = trim($input['shelf_location'] ?? ($input['ShelfLocation'] ?? ''));

    if ($isbn === '' || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ISBN and Title are required']);
        exit;
    }

    try {
        // Insert using the schema available in database
        $stmt = $pdo->prepare('INSERT INTO Book (ISBN, Title, Author, Genre, PublicationYear, Status, ShelfLocation) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $year = isset($input['year']) ? (int)$input['year'] : null;
        $status = $input['status'] ?? 'Available';
        $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location]);
        http_response_code(201);
        echo json_encode(['success' => true, 'isbn' => $isbn]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'DELETE') {
    // Delete book by ISBN or id
    $isbn = $input['isbn'] ?? $input['ISBN'] ?? null;
    $id = $input['id'] ?? null;
    try {
        if ($isbn) {
            $stmt = $pdo->prepare('DELETE FROM Book WHERE ISBN = ?');
            $stmt->execute([$isbn]);
        } elseif ($id) {
            // attempt to delete by numeric id if table has one
            $stmt = $pdo->prepare('DELETE FROM Book WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Book ISBN or id required']);
            exit;
        }
        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
