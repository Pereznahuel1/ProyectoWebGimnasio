<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/domain.php';require __DIR__.'/layout.php';
$user=current_user();if(!$user)redirect('login.php');
$isAdmin=$user['rol']==='administrador';
$week=($_GET['semana']??'')==='1'?1:0;$dates=fechas_clases($week);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if($isAdmin){http_response_code(403);exit('Esta acción corresponde al alumno.');}
 check_csrf();
 try{$_SESSION['class_flash']=reserva_action($_POST,$user);redirect('clases.php?semana='.$week);}
 catch(InvalidArgumentException|PDOException $e){http_response_code(422);$error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar la reserva. Intentá nuevamente.';}
}
$schedules=rows('SELECT hora,cupo FROM turnos_gimnasio ORDER BY hora');
$profile=$isAdmin?null:one('SELECT * FROM alumnos WHERE usuario_id=?',[$user['id']]);
if(!$isAdmin && !$profile){http_response_code(404);exit('Ficha de alumno no encontrada.');}
$selected=is_string($_GET['fecha']??null)?$_GET['fecha']:date('Y-m-d');
$allowed=array_map(fn($d)=>$d->format('Y-m-d'),$dates);
if(!in_array($selected,$allowed,true))$selected=$dates[1]->format('Y-m-d');
page_header($user,$isAdmin?'Clases':'Mis clases','clases');
?>
<h1 class="mb-3"><?= $isAdmin?'Clases':'Mis clases' ?></h1>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if(isset($_SESSION['class_flash'])): ?><div class="alert alert-success" role="status"><?= escape($_SESSION['class_flash']) ?></div><?php unset($_SESSION['class_flash']);endif; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
<a class="btn <?= $week===0?'btn-primary':'btn-outline-primary' ?>" href="clases.php?semana=0"><?= (int)date('N')===7?'Próxima semana':'Esta semana' ?></a>
<a class="btn <?= $week===1?'btn-primary':'btn-outline-primary' ?>" href="clases.php?semana=1"><?= (int)date('N')===7?'Semana siguiente':'Próxima semana' ?></a>
<span class="text-muted ms-2"><?= $dates[1]->format('d/m') ?> al <?= $dates[6]->format('d/m/Y') ?></span>
</div>
<?php if($isAdmin): ?>
<form method="get" class="row g-3 align-items-end mb-4"><input type="hidden" name="semana" value="<?= $week ?>"><div class="col-md-5"><label for="class-date" class="form-label">Día de clase</label><select class="form-select" name="fecha" id="class-date"><?php foreach($dates as $day=>$date): $value=$date->format('Y-m-d'); ?><option value="<?= $value ?>" <?= $selected===$value?'selected':'' ?>><?= escape(dias_semana()[$day].' '.$date->format('d/m')) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-primary">Ver clases</button></div></form>
<?php $groups=[];foreach($schedules as $slot)$groups[$slot['hora']]=[];foreach(alumnos_clase($selected) as $student)if($student['hora']!==null)$groups[$student['hora']][]=$student; ?>
<div class="row">
<?php foreach($schedules as $slot): $students=$groups[$slot['hora']]; ?>
<section class="col-lg-6" id="horario-<?= escape(str_replace(':','',$slot['hora'])) ?>"><div class="card mb-4"><div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><?= escape($slot['hora']) ?></h2><span class="badge <?= count($students)>=(int)$slot['cupo']?'bg-danger':'bg-primary' ?>"><?= count($students) ?> / <?= (int)$slot['cupo'] ?> alumnos</span></div>
<div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Alumno</th></tr></thead><tbody><?php foreach($students as $student): ?><tr><td><a href="alumnos.php?accion=editar&amp;id=<?= (int)$student['id'] ?>"><?= escape($student['nombre']) ?></a></td></tr><?php endforeach; ?><?php if(!$students): ?><tr><td class="text-muted">Sin alumnos reservados.</td></tr><?php endif; ?></tbody></table></div><div class="card-footer text-muted small"><?= max(0,(int)$slot['cupo']-count($students)) ?> lugares disponibles</div></div></section>
<?php endforeach; ?></div>
<?php else: ?>
<p class="text-muted">Una clase por día, de lunes a sábado. Podés cancelar o cambiar el horario antes de que comience tu clase. Los cambios se aplican solo al día elegido.</p>
<div class="row">
<?php foreach($dates as $day=>$date):
 $value=$date->format('Y-m-d');$hour=clase_alumno($profile,$value);
 $modifiable=$value>=date('Y-m-d') && ($hour===null || clase_modificable($value,$hour));
 $options=[];
 if($modifiable)foreach($schedules as $slot){
  if(!clase_modificable($value,$slot['hora']) || $slot['hora']===$hour)continue;
  $available=(int)$slot['cupo']-cupo_fecha($value,$slot['hora'],(int)$profile['id']);
  if($available>0)$options[]=['hora'=>$slot['hora'],'disponibles'=>$available];
 }
?>
<div class="col-lg-4 col-md-6"><section class="card mb-4"><div class="card-header"><h2 class="h6 mb-0"><?= escape(dias_semana()[$day].' '.$date->format('d/m')) ?></h2></div><div class="card-body">
<p class="h4"><?= escape($hour??'Sin reserva') ?></p><p class="text-muted small"><?= $hour!==null?($modifiable?'Clase reservada':'Clase iniciada o finalizada'):'Podés elegir un horario disponible.' ?></p>
<?php if($modifiable): ?>
<?php if($hour!==null): ?><form method="post" class="mb-3" data-cancel-class="<?= escape(dias_semana()[$day].' '.$date->format('d/m').' a las '.$hour) ?>"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="cancelar"><input type="hidden" name="fecha" value="<?= $value ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Cancelar clase</button></form><?php endif; ?>
<?php if($options): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="reservar"><input type="hidden" name="fecha" value="<?= $value ?>"><label for="hour-<?= $day ?>" class="form-label"><?= $hour!==null?'Cambiar horario':'Reservar horario' ?></label><select class="form-select mb-2" name="hora" id="hour-<?= $day ?>" required><option value="">Seleccionar horario</option><?php foreach($options as $option): ?><option value="<?= escape($option['hora']) ?>"><?= escape($option['hora']) ?> · <?= $option['disponibles'] ?> lugares</option><?php endforeach; ?></select><button class="btn btn-primary btn-sm" type="submit"><?= $hour!==null?'Cambiar horario':'Reservar clase' ?></button></form><?php else: ?><p class="small text-muted mb-0">No hay otros horarios con cupo disponible.</p><?php endif; ?>
<?php endif; ?>
</div></section></div>
<?php endforeach; ?></div>
<dialog id="cancel-class-dialog" class="user-confirm-dialog" aria-labelledby="cancel-title"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="cancel-title">Cancelar clase</h2><button class="btn-close" type="button" aria-label="Cerrar" data-close></button></div><div class="modal-body"><p>¿Querés cancelar la clase del <strong id="cancel-description"></strong>?</p><p class="text-muted mb-0">Tu lugar quedará disponible para otro alumno.</p></div><div class="modal-footer"><button type="button" class="btn btn-light" data-close autofocus>Volver</button><button type="button" class="btn btn-danger" id="cancel-confirm">Cancelar clase</button></div></div></dialog>
<script>
const dialog=document.getElementById('cancel-class-dialog');let pending=null;
document.querySelectorAll('[data-cancel-class]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();pending=form;document.getElementById('cancel-description').textContent=form.dataset.cancelClass;dialog.showModal();}));
dialog.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));dialog.addEventListener('close',()=>pending=null);
document.getElementById('cancel-confirm').addEventListener('click',event=>{if(pending){event.currentTarget.disabled=true;pending.submit();}});
</script>
<?php endif; ?>
<?php page_footer(); ?>