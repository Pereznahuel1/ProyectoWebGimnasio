<?php
require __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
check_csrf();
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $params['path'],
    'secure' => $params['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
redirect('login.php');