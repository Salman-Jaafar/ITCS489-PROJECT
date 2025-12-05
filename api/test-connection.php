<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

echo json_encode([
    'config' => $config,
    'mysql_extension' => extension_loaded('pdo_mysql') ? 'LOADED' : 'NOT LOADED',
    'connection_attempt' => 'Trying to connect...'
], JSON_PRETTY_PRINT);

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    
    echo json_encode([
        'status' => 'SUCCESS',
        'message' => 'Connected to ' . $config['db_name'] . ' at ' . $config['db_host'],
        'tables' => $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_PRETTY_PRINT);
}
?>
