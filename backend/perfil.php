<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/domain.php';require __DIR__.'/layout.php';
$user=require_role('alumno');$profile=one('SELECT * FROM alumnos WHERE usuario_id=?',[$user['id']]);
page_header($user,'Mi perfil','perfil');
?><h1 class="mb-4">Mi perfil</h1>
<div class="card mb-4"><div class="card-header">Datos personales</div><div class="card-body">
<dl class="row mb-0">
<dt class="col-sm-3">Nombre y apellido</dt><dd class="col-sm-9"><?= escape($user['nombre']) ?></dd>
<dt class="col-sm-3">Usuario</dt><dd class="col-sm-9"><?= escape($user['usuario']) ?></dd>
<dt class="col-sm-3">Teléfono</dt><dd class="col-sm-9"><?= escape($profile['telefono']??'Sin teléfono registrado') ?></dd>
<dt class="col-sm-3">Horario habitual</dt><dd class="col-sm-9"><?= escape($profile['horario_habitual']??'Sin asignar') ?></dd>
<dt class="col-sm-3">Fecha de alta</dt><dd class="col-sm-9"><?= isset($profile['fecha_alta'])?escape(date('d/m/Y',strtotime($profile['fecha_alta']))):'Sin fecha registrada' ?></dd>
</dl><p class="text-muted mt-3 mb-0">Para actualizar tus datos, contactá a la administración.</p><a class="btn btn-outline-primary mt-3" href="contrasena.php">Cambiar mi contraseña</a>
</div></div>
<?php page_footer(); ?>