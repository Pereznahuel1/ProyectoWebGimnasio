<?php
if (!isset($user,$title)) { http_response_code(404);exit; }
require_once __DIR__.'/layout.php';
page_header($user,$title,$user['rol']==='administrador'?'dashboard':'perfil');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
<h1 class="mb-0"><?= escape($title) ?></h1>
<?php if($user['rol']==='administrador'): ?>
<div class="d-flex flex-wrap gap-2">
<a class="btn btn-primary" href="alumnos.php?accion=nuevo">Nuevo alumno</a>
<a class="btn btn-outline-primary" href="alumnos.php">Gestionar alumnos</a>
</div>
<?php endif; ?>
</div>
<ol class="breadcrumb mb-4"><li class="breadcrumb-item active"><?= $user['rol']==='administrador'?'Resumen del gimnasio':'Mi perfil' ?></li></ol>
<?php if($user['rol']==='administrador'): ?>
<div class="row">
<?php foreach([
 ['Alumnos activos',$activeStudents,'bg-primary text-white','alumnos.php?estado=1','Ver alumnos'],
 ['Días con rutina publicada',$publishedRoutines,'bg-success text-white','rutinas.php','Ver rutinas'],
 ['Horarios del gimnasio',count($scheduleSummary),'bg-warning text-dark','horarios.php','Ver horarios'],
 ['Lugares disponibles',array_sum(array_column($scheduleSummary,'cupo'))-$assignedStudents,'bg-info text-dark','clases.php','Ver alumnos por horario']
] as [$label,$value,$color,$url,$link]): ?>
<div class="col-xl-3 col-md-6"><div class="card <?= escape($color) ?> mb-4">
<div class="card-body"><div><?= escape($label) ?></div><div class="display-6"><?= (int)$value ?></div></div>
<div class="card-footer"><a class="small <?= str_contains($color,'text-white')?'text-white':'text-dark' ?> stretched-link" href="<?= escape($url) ?>"><?= escape($link) ?></a></div>
</div></div>
<?php endforeach; ?>
</div>
<div class="row">
<section class="col-xl-7"><div class="card mb-4"><div class="card-header">Cuotas</div><div class="card-body">
<p>Vencimiento: día 10 de cada mes.</p>
<div class="d-flex flex-wrap gap-3 mb-3"><span class="badge bg-success">Al día: <?= (int)$feeStats['al_dia'] ?></span><span class="badge bg-danger">Vencidas: <?= (int)$feeStats['vencidas'] ?></span></div>
<a href="cuotas.php">Ver listado de cuotas</a>
</div></div>
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0"><?= $dashboardWeekday===7?'Rutina del próximo lunes':'Rutina de hoy' ?></h2>
        <span class="badge <?= trim($dashboardRoutineText)!==''?'bg-success':'bg-secondary' ?>"><?= trim($dashboardRoutineText)!==''?'Publicada':'Pendiente' ?></span>
    </div>
    <div class="card-body">
        <p class="fw-semibold mb-3"><?= escape(dias_semana()[$dashboardRoutineDay].' '.$dashboardRoutineDate->format('d/m')) ?></p>
        <?php if($dashboardWeekday===7): ?><p class="text-muted small">Hoy es domingo y no hay clases. Esta es la rutina prevista para el lunes.</p><?php endif; ?>
        <?php if(trim($dashboardRoutineText)!==''): ?>
        <div class="routine-text dashboard-routine-preview" tabindex="0" role="region" aria-label="Vista previa de la rutina"><?= escape($dashboardRoutineText) ?></div>
        <?php else: ?>
        <p class="text-muted mb-0">Todavía no hay una rutina cargada para este día.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-light"><a class="btn btn-sm btn-outline-primary" href="rutinas.php?dia=<?= $dashboardRoutineDay ?>"><?= trim($dashboardRoutineText)!==''?'Ver o editar rutina':'Cargar rutina' ?></a></div>
</div>
</section>
<section class="col-xl-5"><div class="card mb-4"><div class="card-header">Horarios del gimnasio</div><div class="card-body table-responsive">
<table class="table"><thead><tr><th>Horario</th><th>Alumnos</th><th>Lugares disponibles</th></tr></thead><tbody>
<?php foreach($scheduleSummary as $slot): ?><tr><td><a href="clases.php#horario-<?= escape(str_replace(':','',$slot['hora'])) ?>"><?= escape($slot['hora']) ?></a></td><td><?= (int)$slot['inscriptos'] ?> / <?= (int)$slot['cupo'] ?></td><td><?= (int)$slot['cupo']-(int)$slot['inscriptos'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></section>
</div>

<?php else: ?>
<div class="card mb-4"><div class="card-header">Datos personales</div><div class="card-body">
<dl class="row mb-0">
<dt class="col-sm-3">Nombre y apellido</dt><dd class="col-sm-9"><?= escape($user['nombre']) ?></dd>
<dt class="col-sm-3">Usuario</dt><dd class="col-sm-9"><?= escape($user['usuario']) ?></dd>
<dt class="col-sm-3">Teléfono</dt><dd class="col-sm-9"><?= escape($profile['telefono']??'Sin teléfono registrado') ?></dd>
<dt class="col-sm-3">Horario habitual</dt><dd class="col-sm-9"><?= escape($profile['horario_habitual']??'Sin asignar') ?></dd>
<dt class="col-sm-3">Fecha de alta</dt><dd class="col-sm-9"><?= isset($profile['fecha_alta'])?escape(date('d/m/Y',strtotime($profile['fecha_alta']))):'Sin fecha registrada' ?></dd>
</dl><p class="text-muted mt-3 mb-0">Para actualizar tus datos, contactá a la administración.</p><a class="btn btn-outline-primary mt-3" href="contrasena.php">Cambiar mi contraseña</a>
</div></div>
<div class="row">
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Mi cuota</div><div class="card-body">
<p><span class="badge <?= $feeCurrent?'bg-success':'bg-danger' ?>"><?= $feeCurrent?'Al día':'Vencida' ?></span></p><a href="cuotas.php">Ver mi cuota</a>
</div></div></div>
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Rutina de hoy</div><div class="card-body">
<p><?= (int)date('N')===7?'Hoy no hay clases.':(!empty($routine['contenido'])?'La rutina de '.escape(dias_semana()[(int)date('N')]).' ya está disponible.':'Todavía no se cargó la rutina de hoy.') ?></p><a href="rutinas.php">Ver rutinas de la semana</a>
</div></div></div>
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Mis clases</div><div class="card-body"><p>Consultá tu horario habitual del gimnasio.</p><a href="clases.php">Ver mis clases</a></div></div></div>
</div>
<?php endif; ?>
<?php page_footer(); ?>


