<?php
// Debug logging
error_log("books.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);

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

// Check for method override (for PUT via POST with _method parameter)
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
    $method = 'PUT';
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    // Get one or many books. Accept ?isbn= or return list
    try {
        if (isset($_GET['isbn'])) {
            $isbn = $_GET['isbn'];
            $stmt = $pdo->prepare('SELECT ISBN as isbn, Title as title, Author as author, PublicationYear as year, Genre as category, Status as status, ShelfLocation as shelf_location, CoverImage as cover_image, Description as description, Publisher as publisher, Pages as pages FROM Book WHERE ISBN = ? LIMIT 1');
            $stmt->execute([$isbn]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$book) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Book not found']); exit; }
            http_response_code(200); echo json_encode($book);
            exit;
        }

        // list
        $stmt = $pdo->query('SELECT ISBN as isbn, Title as title, Author as author, Genre as category, Status as status, ShelfLocation as shelf_location, CoverImage as cover_image FROM Book ORDER BY Title LIMIT 200');
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode(['books' => $books]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Create new book
    // Check if this is a file upload (multipart/form-data) or JSON
    // If $_POST has data, it's form data; otherwise it's JSON
    $isFileUpload = !empty($_POST) || isset($_FILES['cover_image']);
    
    if ($isFileUpload) {
        // Handle multipart form data
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $shelf_location = trim($_POST['shelf_location'] ?? '');
        $year = isset($_POST['year']) && $_POST['year'] !== '' ? (int)$_POST['year'] : null;
        $status = $_POST['status'] ?? 'Available';
        $description = trim($_POST['description'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $pages = isset($_POST['pages']) && $_POST['pages'] !== '' ? (int)$_POST['pages'] : null;
        
        // Handle file upload
        $coverImageName = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                // Generate unique filename
                $coverImageName = 'book_' . uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $coverImageName;
                
                // Check if GD extension is available for resizing
                if (extension_loaded('gd')) {
                    // Resize and save image
                    $sourceImage = null;
                    switch ($fileExtension) {
                        case 'jpg':
                        case 'jpeg':
                            $sourceImage = @imagecreatefromjpeg($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'png':
                            $sourceImage = @imagecreatefrompng($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'gif':
                            $sourceImage = @imagecreatefromgif($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'webp':
                            $sourceImage = @imagecreatefromwebp($_FILES['cover_image']['tmp_name']);
                            break;
                    }
                    
                    if ($sourceImage) {
                        // Get original dimensions
                        $origWidth = imagesx($sourceImage);
                        $origHeight = imagesy($sourceImage);
                        
                        // Target dimensions (max 400x600, maintain aspect ratio)
                        $maxWidth = 400;
                        $maxHeight = 600;
                        
                        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                        $newWidth = (int)($origWidth * $ratio);
                        $newHeight = (int)($origHeight * $ratio);
                        
                        // Create resized image
                        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                        
                        // Preserve transparency for PNG/GIF
                        if ($fileExtension === 'png' || $fileExtension === 'gif') {
                            imagealphablending($resizedImage, false);
                            imagesavealpha($resizedImage, true);
                        }
                        
                        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                        
                        // Save resized image
                        switch ($fileExtension) {
                            case 'jpg':
                            case 'jpeg':
                                imagejpeg($resizedImage, $uploadPath, 90);
                                break;
                            case 'png':
                                imagepng($resizedImage, $uploadPath, 8);
                                break;
                            case 'gif':
                                imagegif($resizedImage, $uploadPath);
                                break;
                            case 'webp':
                                imagewebp($resizedImage, $uploadPath, 90);
                                break;
                        }
                        
                        imagedestroy($sourceImage);
                        imagedestroy($resizedImage);
                    } else {
                        // GD available but image creation failed - just move file
                        move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath);
                    }
                } else {
                    // GD not available - just move the file without resizing
                    move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath);
                }
            }
        }
    } else {
        // Handle JSON data (backward compatibility)
        $title = trim($input['title'] ?? '');
        $author = trim($input['author'] ?? '');
        $isbn = trim($input['isbn'] ?? ($input['ISBN'] ?? ''));
        $category = trim($input['category'] ?? ($input['genre'] ?? ''));
        $shelf_location = trim($input['shelf_location'] ?? ($input['ShelfLocation'] ?? ''));
        $year = isset($input['year']) ? (int)$input['year'] : null;
        $status = $input['status'] ?? 'Available';
        $description = trim($input['description'] ?? '');
        $publisher = trim($input['publisher'] ?? '');
        $pages = isset($input['pages']) && $input['pages'] !== '' ? (int)$input['pages'] : null;
        $coverImageName = null;
    }
    
    // Basic validation
    if ($isbn === '' || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ISBN and Title are required']);
        exit;
    }

    try {
        // Check if table has CoverImage column
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM Book LIKE 'CoverImage'");
        $hasCoverColumn = $columnsStmt->rowCount() > 0;
        
        if ($hasCoverColumn && $coverImageName) {
            // Insert with cover image
            $stmt = $pdo->prepare('INSERT INTO Book (ISBN, Title, Author, Genre, PublicationYear, Status, ShelfLocation, CoverImage, Description, Publisher, Pages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location, $coverImageName, $description, $publisher, $pages]);
        } elseif ($hasCoverColumn) {
            // Insert with null cover
            $stmt = $pdo->prepare('INSERT INTO Book (ISBN, Title, Author, Genre, PublicationYear, Status, ShelfLocation, Description, Publisher, Pages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location, $description, $publisher, $pages]);
        } else {
            // Table doesn't have CoverImage column - add it
            $pdo->exec("ALTER TABLE Book ADD COLUMN CoverImage VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE Book ADD COLUMN Description TEXT DEFAULT NULL");
            $pdo->exec("ALTER TABLE Book ADD COLUMN Publisher VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE Book ADD COLUMN Pages INT DEFAULT NULL");
            
            // Now insert
            $stmt = $pdo->prepare('INSERT INTO Book (ISBN, Title, Author, Genre, PublicationYear, Status, ShelfLocation, CoverImage, Description, Publisher, Pages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location, $coverImageName, $description, $publisher, $pages]);
        }
        
        http_response_code(201);
        echo json_encode(['success' => true, 'isbn' => $isbn, 'title' => $title, 'cover_image' => $coverImageName]);
    } catch (PDOException $e) {
        // Check if it's a duplicate key error
        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A book with ISBN "' . $isbn . '" already exists in the database. Please use a different ISBN.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
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
} elseif ($method === 'PUT') {
    // Update existing book
    $isFileUpload = !empty($_POST) || isset($_FILES['cover_image']);
    
    if ($isFileUpload) {
        // Handle multipart form data (from edit form)
        $originalISBN = trim($_POST['original_isbn'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $shelf_location = trim($_POST['shelf_location'] ?? '');
        $year = isset($_POST['year']) && $_POST['year'] !== '' ? (int)$_POST['year'] : null;
        $status = $_POST['status'] ?? 'Available';
        $description = trim($_POST['description'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $pages = isset($_POST['pages']) && $_POST['pages'] !== '' ? (int)$_POST['pages'] : null;
        
        // Handle file upload (new cover image)
        $coverImageName = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $coverImageName = 'book_' . uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $coverImageName;
                
                // Check if GD extension is available for resizing
                if (extension_loaded('gd')) {
                    $sourceImage = null;
                    switch ($fileExtension) {
                        case 'jpg':
                        case 'jpeg':
                            $sourceImage = @imagecreatefromjpeg($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'png':
                            $sourceImage = @imagecreatefrompng($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'gif':
                            $sourceImage = @imagecreatefromgif($_FILES['cover_image']['tmp_name']);
                            break;
                        case 'webp':
                            $sourceImage = @imagecreatefromwebp($_FILES['cover_image']['tmp_name']);
                            break;
                    }
                    
                    if ($sourceImage) {
                        $origWidth = imagesx($sourceImage);
                        $origHeight = imagesy($sourceImage);
                        
                        $maxWidth = 400;
                        $maxHeight = 600;
                        
                        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                        $newWidth = (int)($origWidth * $ratio);
                        $newHeight = (int)($origHeight * $ratio);
                        
                        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                        
                        if ($fileExtension === 'png' || $fileExtension === 'gif') {
                            imagealphablending($resizedImage, false);
                            imagesavealpha($resizedImage, true);
                        }
                        
                        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                        
                        switch ($fileExtension) {
                            case 'jpg':
                            case 'jpeg':
                                imagejpeg($resizedImage, $uploadPath, 90);
                                break;
                            case 'png':
                                imagepng($resizedImage, $uploadPath, 8);
                                break;
                            case 'gif':
                                imagegif($resizedImage, $uploadPath);
                                break;
                            case 'webp':
                                imagewebp($resizedImage, $uploadPath, 90);
                                break;
                        }
                        
                        imagedestroy($sourceImage);
                        imagedestroy($resizedImage);
                    } else {
                        move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath);
                    }
                } else {
                    move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath);
                }
            }
        }
    } else {
        // Handle JSON data
        $originalISBN = trim($input['original_isbn'] ?? '');
        $title = trim($input['title'] ?? '');
        $author = trim($input['author'] ?? '');
        $isbn = trim($input['isbn'] ?? '');
        $category = trim($input['category'] ?? '');
        $shelf_location = trim($input['shelf_location'] ?? '');
        $year = isset($input['year']) && $input['year'] !== '' ? (int)$input['year'] : null;
        $status = $input['status'] ?? 'Available';
        $description = trim($input['description'] ?? '');
        $publisher = trim($input['publisher'] ?? '');
        $pages = isset($input['pages']) && $input['pages'] !== '' ? (int)$input['pages'] : null;
        $coverImageName = null;
    }
    
    // Validation
    if ($originalISBN === '' || $isbn === '' || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Original ISBN, ISBN and Title are required']);
        exit;
    }
    
    try {
        // Build UPDATE query
        if ($coverImageName) {
            // Update with new cover image
            $stmt = $pdo->prepare('UPDATE Book SET ISBN = ?, Title = ?, Author = ?, Genre = ?, PublicationYear = ?, Status = ?, ShelfLocation = ?, CoverImage = ?, Description = ?, Publisher = ?, Pages = ? WHERE ISBN = ?');
            $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location, $coverImageName, $description, $publisher, $pages, $originalISBN]);
        } else {
            // Update without changing cover image
            $stmt = $pdo->prepare('UPDATE Book SET ISBN = ?, Title = ?, Author = ?, Genre = ?, PublicationYear = ?, Status = ?, ShelfLocation = ?, Description = ?, Publisher = ?, Pages = ? WHERE ISBN = ?');
            $stmt->execute([$isbn, $title, $author, $category, $year, $status, $shelf_location, $description, $publisher, $pages, $originalISBN]);
        }
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Book not found or no changes made']);
            exit;
        }
        
        http_response_code(200);
        echo json_encode(['success' => true, 'isbn' => $isbn, 'title' => $title]);
    } catch (PDOException $e) {
        // Check for duplicate key
        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A book with ISBN "' . $isbn . '" already exists.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>

