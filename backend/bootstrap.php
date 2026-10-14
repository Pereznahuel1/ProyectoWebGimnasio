<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $config = require __DIR__ . '/config.php';
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('gym_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('La sesión del formulario venció. Volvé a cargar la página.');
    }
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $query = db()->prepare('SELECT id, nombre, usuario, rol FROM usuarios WHERE id = ? AND activo = 1');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user ?: null;
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if ($user['rol'] !== $role) {
        http_response_code(403);
        exit('No tenés permiso para acceder a esta página.');
    }
    return $user;
}
function valid_username(string $username): bool
{
    return preg_match('/\A[A-Za-z0-9_.-]{3,50}\z/', $username) === 1;
}
function hash_password(string $password): string {
    if($password==='') throw new InvalidArgumentException('Ingresá una contraseña.');
    return password_hash($password,PASSWORD_ARGON2ID);
}
