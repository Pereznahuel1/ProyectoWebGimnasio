<?php
require __DIR__ . '/bootstrap.php';
$user = current_user();
if (!$user) {
    redirect('login.php');
}
redirect($user['rol'] === 'administrador' ? 'admin.php' : 'alumno.php');