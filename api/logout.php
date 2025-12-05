<?php
session_start();
header('Content-Type: application/json');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
