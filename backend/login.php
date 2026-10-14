<?php
require __DIR__ . '/bootstrap.php';
$error = '';
$usuario = '';
try {
    if (current_user()) {
        redirect('index.php');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $query = db()->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = ? AND activo = 1');
        $query->execute([$usuario]);
        $user = $query->fetch();
        if (!valid_username($usuario)) { $user = false; }
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect('index.php');
        }
        $error = 'El usuario o la contraseña no son correctos.';
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    $error = 'No se pudo conectar con la base de datos';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SISTEMA FIT</title>
    <link href="css/styles.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/img/fit-control.svg">
    <link href="css/gym.css?v=<?= filemtime(__DIR__.'/css/gym.css') ?>" rel="stylesheet">
</head>
<body class="bg-dark login-page">
<main class="container login-container">
    <h1 class="login-heading">SISTEMA DE GESTIÓN PARA GIMNASIOS</h1>
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header"><h2 class="h3 my-4 fit-brand justify-content-center"><img class="fit-logo" src="assets/img/fit-control.svg" width="36" height="36" alt=""><span>SISTEMA FIT</span></h2></div>
                <div class="card-body">
                    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input class="form-control" id="usuario" name="usuario" type="text" autocomplete="username" required maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" title="Entre 3 y 50 letras sin tildes, números, puntos, guiones o guiones bajos" autocapitalize="none" spellcheck="false" value="<?= escape($usuario) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Iniciar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <p class="text-center text-white-50 small mt-4 mb-0"> © SISTEMA FIT 2026</p>
</main>
</body>
</html>