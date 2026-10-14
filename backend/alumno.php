<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/domain.php';require __DIR__.'/layout.php';
$user=require_role('alumno');
$profile=one('SELECT * FROM alumnos WHERE usuario_id=?',[$user['id']]);
$nextClass=null;
if($profile)foreach([0,1] as $week){foreach(fechas_clases($week) as $date){$dateText=$date->format('Y-m-d');$hour=clase_alumno($profile,$dateText);if($hour!==null && clase_modificable($dateText,$hour)){$nextClass=['fecha'=>$date->format('d/m'),'hora'=>$hour];break 2;}}}
$feeCurrent=cuota_al_dia($profile['ultimo_mes_abonado']??null);
$routine=(int)date('N')===7?null:one('SELECT contenido FROM rutinas_semanales WHERE dia=?',[(int)date('N')]);
$notices=rows('SELECT id,contenido,formato_html,creado_en,actualizado_en,(imagen IS NOT NULL) tiene_imagen FROM avisos ORDER BY creado_en DESC,id DESC');
page_header($user,'Mi dashboard','dashboard');
?>

<h1 class="mb-4">¡Hola, <?= escape($profile['nombres']??$user['nombre']) ?>!</h1>
<div class="row">
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Mi cuota</div><div class="card-body">
<p><span class="badge <?= $feeCurrent?'bg-success':'bg-danger' ?>"><?= $feeCurrent?'Al día':'Vencida' ?></span></p><a href="cuotas.php">Ver mi cuota</a>
</div></div></div>
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Rutina de hoy</div><div class="card-body">
<p><?= (int)date('N')===7?'Hoy no hay clases.':(!empty($routine['contenido'])?'La rutina de '.escape(dias_semana()[(int)date('N')]).' ya está disponible.':'Todavía no se cargó la rutina de hoy.') ?></p><a href="rutinas.php">Ver rutinas de la semana</a>
</div></div></div>
<div class="col-md-4"><div class="card mb-4"><div class="card-header">Mis clases</div><div class="card-body"><p class="h4"><?= $nextClass?escape($nextClass['fecha'].' · '.$nextClass['hora']):'Sin próximas reservas' ?></p><p class="text-muted small">Próxima clase · De lunes a sábado</p><a href="clases.php">Ver mis clases</a></div></div></div>
</div>

<h2 class="h4 mb-3">Avisos del gimnasio</h2>
<?php if(!$notices): ?><div class="card"><div class="card-body text-muted">Todavía no hay avisos publicados.</div></div><?php endif; ?>
<div class="row">
<?php foreach($notices as $notice): ?>
<div class="col-lg-6"><article class="card mb-4">
<div class="card-header">Aviso del <?= escape(date('d/m/Y H:i',strtotime($notice['creado_en']))) ?></div>
<div class="card-body">
<?php if($notice['tiene_imagen']): ?><img class="notice-image mb-3" src="aviso-imagen.php?id=<?= (int)$notice['id'] ?>" alt="Imagen adjunta al aviso del gimnasio" loading="lazy"><?php endif; ?>
<?php if($notice['contenido']!==''): ?><div class="notice-text mb-3"><?= notice_html($notice) ?></div><?php endif; ?>
</div></article></div>
<?php endforeach; ?></div>
<?php page_footer(); ?>
