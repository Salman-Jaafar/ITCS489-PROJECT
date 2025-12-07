<?php
/**
 * Project Structure Verification Script
 * Checks all critical paths and database connections
 */

require __DIR__ . '/../db.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        ITCS489-PROJECT STRUCTURE & PATH VERIFICATION           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// 1. Verify directory structure
echo "1. DIRECTORY STRUCTURE\n";
echo "─────────────────────────────────────────────────────────────────\n";

$directories = [
    'api' => 'API endpoints and database operations',
    'api/utils' => 'Utility scripts for database management',
    'templates' => 'HTML templates for user interface',
    'css' => 'Stylesheets',
    'js' => 'JavaScript files',
    'images' => 'Image assets',
    'uploads' => 'User uploads (profile photos, book covers)',
    'database' => 'Database backup/migration files',
    'data' => 'JSON data files',
];

$baseDir = __DIR__ . '/../..';
foreach ($directories as $dir => $desc) {
    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    $exists = is_dir($fullPath) ? '✓' : '✗';
    echo "{$exists} {$dir}: {$desc}\n";
}

echo "\n2. API FILES (Core)\n";
echo "─────────────────────────────────────────────────────────────────\n";

$apiFiles = [
    'api/db.php' => 'Database connection',
    'api/config.php' => 'Database configuration',
    'api/login.php' => 'User login endpoint',
    'api/signup.php' => 'User registration endpoint',
    'api/books.php' => 'Books management endpoint',
    'api/reservations.php' => 'Book reservations endpoint',
    'api/profile.php' => 'User profile endpoint',
    'api/change-password.php' => 'Password change endpoint',
    'api/user_summary.php' => 'User dashboard summary',
    'api/admin_summary.php' => 'Admin dashboard summary',
];

foreach ($apiFiles as $file => $desc) {
    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $exists = file_exists($fullPath) ? '✓' : '✗';
    echo "{$exists} {$file}: {$desc}\n";
}

echo "\n3. HTML TEMPLATES\n";
echo "─────────────────────────────────────────────────────────────────\n";

$templates = [
    'templates/login.html' => 'User login page',
    'templates/signup.html' => 'User registration page',
    'templates/user-dashboard.html' => 'User main dashboard',
    'templates/user-inventory.html' => 'User book inventory',
    'templates/books.html' => 'Browse all books',
    'templates/book-details.html' => 'Book details page',
    'templates/profile.html' => 'User profile page',
    'templates/admin-dashboard.html' => 'Admin dashboard',
    'templates/manage-books.html' => 'Book management (admin)',
];

foreach ($templates as $file => $desc) {
    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $exists = file_exists($fullPath) ? '✓' : '✗';
    echo "{$exists} {$file}: {$desc}\n";
}

echo "\n4. DATABASE VERIFICATION\n";
echo "─────────────────────────────────────────────────────────────────\n";

try {
    // Check database connection
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Database connected: {$result['db_name']}\n";
    
    // Check tables
    $tables = ['User', 'Employee', 'Book', 'Reservation', 'ReservationItem', 'Loan', 'LoanItem'];
    echo "\n  Tables:\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0 ? '✓' : '✗';
        echo "  {$exists} {$table}\n";
    }
    
    // Check users
    echo "\n  Records Count:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  ✓ Total users: {$result['count']}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Book");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  ✓ Total books: {$result['count']}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Reservation");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  ✓ Total reservations: {$result['count']}\n";
    
} catch (Exception $e) {
    echo "✗ Database error: {$e->getMessage()}\n";
}

echo "\n5. ASSET PATHS (CSS, JS, Images)\n";
echo "─────────────────────────────────────────────────────────────────\n";

$assets = [
    'css/style.css',
    'css/normalize.css',
    'css/vendor.css',
    'css/lms-custom.css',
    'js/jquery-1.11.0.min.js',
    'js/plugins.js',
    'js/script.js',
    'images/main-logo.png',
];

foreach ($assets as $file) {
    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $exists = file_exists($fullPath) ? '✓' : '✗';
    echo "{$exists} {$file}\n";
}

echo "\n6. UPLOAD DIRECTORIES\n";
echo "─────────────────────────────────────────────────────────────────\n";

$uploadDirs = [
    'uploads/profiles' => 'User profile photos',
    'uploads' => 'Book cover images',
];

foreach ($uploadDirs as $dir => $desc) {
    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    $readable = is_writable($fullPath) ? '✓ writable' : '✗ not writable';
    echo "✓ {$dir} ({$desc}): {$readable}\n";
}

echo "\n═════════════════════════════════════════════════════════════════\n";
echo "✓ Project structure verification complete!\n";
echo "═════════════════════════════════════════════════════════════════\n";

?>
