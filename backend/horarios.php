<?php
$module='horarios';require __DIR__.'/module.php';
$edit=is_string($_GET['editar']??null)?$_GET['editar']:'';
$record=$edit!==''?one('SELECT hora,cupo FROM turnos_gimnasio WHERE hora=?',[$edit]):null;
if($edit!=='' && !$record){http_response_code(404);exit('Horario no encontrado.');}
$values=$record??['hora'=>'','cupo'=>20];
if($error && ($_POST['operacion']??'')!=='eliminar')foreach(['hora','cupo'] as $key)if(is_string($_POST[$key]??null))$values[$key]=$_POST[$key];
page_intro('Horarios');
card_start($record?'Editar horario':'Nuevo horario');
form_start($record?'editar':'crear',$record?['original'=>$record['hora']]:[]);
input_field('hora','Hora','time',(string)$values['hora'],true,'step="60"');
input_field('cupo','Cupo máximo','number',(string)$values['cupo'],true,'min="1" max="10000" step="1"');
form_end($record?'Guardar cambios':'Agregar horario');
if($record)echo '<a class="btn btn-link mt-2" href="horarios.php">Cancelar edición</a>';
card_end();
card_start('Horarios del gimnasio');
table_start(['Horario','Cupo máximo','Alumnos activos','Disponibles','Acciones']);
$schedules=listado_horarios();
foreach($schedules as $slot){
 echo '<tr>';cell($slot['hora']);cell((string)$slot['cupo']);cell((string)$slot['inscriptos']);cell((string)((int)$slot['cupo']-(int)$slot['inscriptos']));
 echo '<td><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="horarios.php?editar='.rawurlencode($slot['hora']).'">Editar</a><form method="post" action="horarios.php" class="m-0" data-delete-schedule="'.escape($slot['hora']).'"><input type="hidden" name="csrf" value="'.escape($_SESSION['csrf']).'"><input type="hidden" name="operacion" value="eliminar"><input type="hidden" name="original" value="'.escape($slot['hora']).'"><button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button></form></div></td></tr>';
}
if(!$schedules)echo '<tr><td colspan="5" class="text-muted">Todavía no hay horarios. Agregá uno para asignar alumnos.</td></tr>';
table_end();card_end();
?>
<dialog id="schedule-dialog" class="user-confirm-dialog" aria-labelledby="schedule-title" aria-describedby="schedule-description">
<div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="schedule-title">Eliminar horario</h2><button class="btn-close" type="button" aria-label="Cerrar" data-cancel></button></div>
<div class="modal-body" id="schedule-description"><p>¿Querés eliminar el horario de las <strong id="schedule-hour"></strong>?</p><div class="user-confirm-warning">Solo se pueden eliminar horarios sin alumnos asignados.</div></div>
<div class="modal-footer"><button class="btn btn-light" type="button" data-cancel autofocus>Cancelar</button><button class="btn btn-danger" type="button" id="schedule-confirm">Eliminar horario</button></div></div>
</dialog>
<script>
const dialog=document.getElementById('schedule-dialog');
let pending=null;
document.querySelectorAll('[data-delete-schedule]').forEach(form=>form.addEventListener('submit',event=>{
 event.preventDefault();pending=form;
 document.getElementById('schedule-hour').textContent=form.dataset.deleteSchedule;
 document.getElementById('schedule-confirm').disabled=false;
 dialog.showModal();
}));
dialog.querySelectorAll('[data-cancel]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
dialog.addEventListener('close',()=>{pending=null;});
document.getElementById('schedule-confirm').addEventListener('click',event=>{
 if(!pending)return;
 event.currentTarget.disabled=true;
 pending.submit();
});
</script>
<?php page_footer(); ?>