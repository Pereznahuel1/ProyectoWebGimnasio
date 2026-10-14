<?php
declare(strict_types=1);
if (!function_exists('escape')) { http_response_code(404); exit; }

function page_header(array $user, string $title, string $section): void {
    $admin = $user['rol'] === 'administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($title) ?> · SISTEMA FIT</title>
    <link href="css/styles.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/img/fit-control.svg">
    <link href="css/gym.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous" defer></script>
</head>
<body class="sb-nav-fixed">
<a class="visually-hidden-focusable position-fixed bg-white p-2" style="z-index:1100" href="#contenido">Ir al contenido</a>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3 fit-brand" href="index.php"><img class="fit-logo fit-logo-light" src="assets/img/fit-control.svg" width="28" height="28" alt=""> <span>SISTEMA FIT</span></a>
    <button class="btn btn-link btn-sm me-3" id="sidebarToggle" type="button" aria-label="Mostrar u ocultar menú" aria-controls="layoutSidenav_nav"><i class="fas fa-bars" aria-hidden="true"></i></button>
    <span class="text-white-50 small ms-auto me-3 d-none d-md-inline"><?= escape($user['nombre']) ?></span>
    <form action="logout.php" method="post" class="ms-auto ms-md-0 me-3">
        <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
        <button class="btn btn-sm btn-outline-light" type="submit">Cerrar sesión</button>
    </form>
</nav>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark" aria-label="Menú principal">
            <div class="sb-sidenav-menu"><div class="nav">
                <?php
                $links = $admin
                    ? [['dashboard','Dashboard','admin.php','tachometer-alt'],['alumnos','Alumnos','alumnos.php','users'],['rutinas','Rutinas','rutinas.php','dumbbell'],['clases','Clases','clases.php','calendar-alt'],['horarios','Horarios','horarios.php','clock'],['cuotas','Cuotas','cuotas.php','credit-card'],['avisos','Avisos','avisos.php','bullhorn'],['usuarios','Usuarios','usuarios.php','user-cog']]
                    : [['dashboard','Dashboard','alumno.php','tachometer-alt'],['perfil','Mi perfil','perfil.php','user'],['rutinas','Rutinas','rutinas.php','dumbbell'],['clases','Mis clases','clases.php','calendar-alt'],['cuotas','Mi cuota','cuotas.php','credit-card']];
                foreach ($links as [$key,$label,$url,$icon]): ?>
                    <a class="nav-link <?= $section === $key ? 'active' : '' ?>" href="<?= escape($url) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>>
                        <div class="sb-nav-link-icon"><i class="fas fa-<?= escape($icon) ?>" aria-hidden="true"></i></div><?= escape($label) ?>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </nav>
    </div>
    <div id="layoutSidenav_content">
        <main id="contenido"><div class="container-fluid px-4 py-4">
<?php
}

function page_footer(): void {
?>
        </div></main>
    </div>
</div>
<script src="js/scripts.js"></script>
</body>
</html>
<?php
}

