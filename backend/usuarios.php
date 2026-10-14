<?php
$module='usuarios';require __DIR__.'/module.php';
$search=is_string($_GET['buscar']??null)?trim($_GET['buscar']):'';
$listUrl='usuarios.php'.($search!==''?'?'.http_build_query(['buscar'=>$search]):'');
$edit=query_id('editar');
$account=$edit?one('SELECT id,nombre,usuario,rol,activo FROM usuarios WHERE id=?',[$edit]):null;
if($edit && !$account){http_response_code(404);exit('Cuenta no encontrada.');}
$values=$account??['nombre'=>'','usuario'=>'','rol'=>'alumno','activo'=>'1'];
$values['horario_habitual']=$edit?(one('SELECT horario_habitual FROM alumnos WHERE usuario_id=?',[$edit])['horario_habitual']??''):'';
if($edit && $values['rol']==='alumno'){
    $personal=one('SELECT * FROM alumnos WHERE usuario_id=?',[$edit]);
    foreach(['apellido','fecha_nacimiento','dni','telefono','correo'] as $key) $values[$key]=$personal[$key]??'';
    $values['nombre']=$personal['nombres']??$values['nombre'];
}
if($error)foreach(['nombre','apellido','fecha_nacimiento','dni','telefono','correo','usuario','rol','activo','horario_habitual'] as $key)if(is_string($_POST[$key]??null))$values[$key]=$_POST[$key];
page_intro('Usuarios');
if(!$edit){

    echo '<dialog id="new-user-dialog" class="user-confirm-dialog user-form-dialog" aria-labelledby="new-user-title"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="new-user-title">Nuevo usuario</h2><button class="btn-close" type="button" aria-label="Cerrar" data-close-new-user></button></div><div class="modal-body">';
    if($error && ($_POST['operacion']??'')==='cuenta')echo '<div class="alert alert-danger" role="alert">'.escape($error).'</div>';
}else card_start('Editar cuenta');
echo '<p class="text-muted">Solo Administrador y Alumno. Para conservar el historial, el rol se define al crear la cuenta.</p>';
form_start('cuenta',$edit?['id'=>$edit]:[]);
if(!$edit)select_field('rol','Rol',['administrador'=>'Administrador','alumno'=>'Alumno'],$values['rol']);
else echo '<div class="col-md-6"><span class="form-label d-block">Rol</span><div class="form-control-plaintext">'.escape(ucfirst($values['rol'])).'</div></div>';
select_field('activo','Estado',['1'=>'Activo','0'=>'Inactivo'],(string)$values['activo']);
echo '<div class="col-12 pt-3"><h2 class="h6 border-bottom pb-2 mb-0">Datos personales</h2></div>';
input_field('nombre',$values['rol']==='alumno'?'Nombre':'Nombre y apellido','text',$values['nombre'],true,'maxlength="120"');
if(!$edit || $values['rol']==='alumno'){
    ob_start();
    input_field('apellido','Apellido','text',$values['apellido']??'',true,'maxlength="59"');
    input_field('fecha_nacimiento','Fecha de nacimiento','date',$values['fecha_nacimiento']??'',true,'min="1900-01-01" max="'.date('Y-m-d').'"');
    input_field('dni','DNI','text',$values['dni']??'',true,'inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8"');
    input_field('telefono','Teléfono','tel',$values['telefono']??'',true,'maxlength="40"');
    input_field('correo','Correo electrónico','email',$values['correo']??'',true,'maxlength="190"');
    echo str_replace('<div class="col-md-6">','<div class="col-md-6" data-student-field>',ob_get_clean());
}
echo '<div class="col-12 pt-3"><h2 class="h6 border-bottom pb-2 mb-0">Datos de acceso</h2></div>';
echo '<div class="col-12" id="account-credentials"><div class="row g-3">';
input_field('usuario','Usuario','text',$values['usuario'],true,'maxlength="50" autocomplete="off"');
input_field('password',$edit?'Nueva contraseña (vacía conserva la actual)':'Contraseña','password','',!$edit,'autocomplete="new-password"');
echo '</div></div>';
if(!$edit)echo '<div class="col-12" id="dni-access-note"><div class="alert alert-info mb-0">El DNI será el usuario y la contraseña inicial. El alumno podrá cambiar su contraseña desde su cuenta.</div></div>';
if(!$edit || $values['rol']==='alumno'){
    echo '<div class="col-md-6" id="horario-cuenta"><label for="horario_habitual" class="form-label">Horario habitual del alumno</label><select id="horario_habitual" name="horario_habitual" class="form-select"><option value="">Seleccionar horario</option>';
    foreach(horarios_gimnasio() as $hora)echo '<option value="'.escape($hora).'" '.($values['horario_habitual']===$hora?'selected':'').'>'.escape($hora).'</option>';
    echo '</select><div class="form-text">Los cupos se configuran en Horarios.</div></div>';
}
if($edit)form_end('Guardar cambios');
else echo '<div class="col-12 d-flex justify-content-end gap-2 border-top pt-3 mt-4"><button class="btn btn-light border" type="button" data-close-new-user>Cancelar</button><button class="btn btn-primary" type="submit">Crear usuario</button></div></form>';
if($edit)echo '<a class="btn btn-link mt-2" href="usuarios.php">Cancelar edición</a>';
if($edit)card_end();
else echo '</div></div></dialog>';
echo '<form method="get" action="usuarios.php" class="row g-3 align-items-end mb-4"><div class="col-lg-8"><label for="user-search" class="form-label">Buscar por nombre o usuario</label><input class="form-control" type="search" name="buscar" id="user-search" value="'.escape($search).'" placeholder="Nombre, apellido o usuario"></div><div class="col-lg-4 d-flex gap-2 flex-wrap"><button class="btn btn-primary" type="submit">Buscar</button><a class="btn btn-outline-secondary" href="usuarios.php">Limpiar</a>'.(!$edit?'<button class="btn btn-primary ms-auto text-nowrap" type="button" id="open-new-user">Nuevo usuario</button>':'').'</div></form>';
$groups=['administrador'=>[], 'alumno'=>[]];
$sql='SELECT id,nombre,usuario,rol,activo FROM usuarios';
$params=[];
if($search!==''){$sql.=' WHERE LOCATE(?,nombre)>0 OR LOCATE(?,usuario)>0';$params=[$search,$search];}
foreach(rows($sql.' ORDER BY nombre COLLATE utf8mb4_spanish_ci,usuario,id',$params) as $row){
    $groups[$row['rol']][]=$row;
}
echo '<div class="user-account-tables">';
foreach(['administrador'=>'Administradores','alumno'=>'Alumnos'] as $role=>$heading){
    card_start($heading);
    table_start(['Nombre','Usuario','Estado','Acciones']);
    foreach($groups[$role] as $row){
        echo '<tr>';cell($row['nombre']);cell($row['usuario']);cell($row['activo']?'Activo':'Inactivo');
        echo '<td><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="usuarios.php?editar='.(int)$row['id'].'">Editar</a>';
        if((int)$row['id']!==(int)$user['id']){
            $operation=$row['activo']?'deshabilitar':'habilitar';
            echo '<form method="post" action="usuarios.php" class="m-0"'.($row['activo']?' data-disable-user="'.escape($row['nombre']).'"':'').'><input type="hidden" name="csrf" value="'.escape($_SESSION['csrf']).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn btn-sm btn-outline-warning" name="operacion" value="'.$operation.'">'.($row['activo']?'Deshabilitar':'Habilitar').'</button></form>';
            echo '<form method="post" action="usuarios.php" class="m-0" data-delete-user="'.escape($row['nombre']).'"><input type="hidden" name="csrf" value="'.escape($_SESSION['csrf']).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn btn-sm btn-outline-danger" name="operacion" value="eliminar">Eliminar</button></form>';
        }
        if($row['rol']==='alumno'){
            echo '<form method="post" action="'.escape($listUrl).'" class="m-0" data-reset-user="'.escape($row['nombre']).'"><input type="hidden" name="csrf" value="'.escape($_SESSION['csrf']).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn btn-sm btn-outline-primary" name="operacion" value="restablecer">Restablecer contraseña</button></form>';
        }
        echo '</div></td></tr>';
    }
    if(!$groups[$role])echo '<tr><td colspan="4" class="text-muted">No hay usuarios que coincidan en este grupo.</td></tr>';
    table_end();card_end();
}
echo '</div>';
?>
<dialog id="delete-user-dialog" class="user-confirm-dialog" aria-labelledby="delete-user-title" aria-describedby="delete-user-description">
    <div class="modal-content">
        <div class="modal-header">
            <div class="user-confirm-heading">
                <span class="user-confirm-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.1 2.5 17.5A2 2 0 0 0 4.2 20.5h15.6a2 2 0 0 0 1.7-3L13.7 4.1a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 3h.01"/></svg></span>
                <h2 class="modal-title fs-5" id="delete-user-title">Eliminar usuario</h2>
            </div>
            <button type="button" class="btn-close" aria-label="Cerrar" data-cancel-delete></button>
        </div>
        <div class="modal-body" id="delete-user-description">
            <p><span id="user-action-question">¿Querés eliminar definitivamente a</span> <strong id="delete-user-name"></strong>?</p>
            <p class="text-muted">Se borrarán su cuenta y sus datos asociados.</p>
            <div class="user-confirm-warning">Esta acción es permanente y no se puede deshacer.</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-cancel-delete autofocus>Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirm-delete-user">Eliminar usuario</button>
        </div>
    </div>
</dialog>
<script>
const deleteDialog=document.getElementById('delete-user-dialog');
const deleteName=document.getElementById('delete-user-name');
const deleteConfirm=document.getElementById('confirm-delete-user');
let pendingDelete=null;
let confirmedDelete=null;
document.querySelectorAll('form[data-delete-user], form[data-disable-user], form[data-reset-user]').forEach(form=>{
    form.addEventListener('submit',event=>{
        if(confirmedDelete===form){ confirmedDelete=null; return; }
        event.preventDefault();
        pendingDelete=form;
        const disabling=form.hasAttribute('data-disable-user');
        const resetting=form.hasAttribute('data-reset-user');
        deleteName.textContent=resetting?form.dataset.resetUser:disabling?form.dataset.disableUser:form.dataset.deleteUser;
        deleteDialog.classList.toggle('user-confirm-disable',disabling||resetting);
        document.getElementById('delete-user-title').textContent=resetting?'Restablecer contraseña':disabling?'Deshabilitar usuario':'Eliminar usuario';
        document.getElementById('user-action-question').textContent=resetting?'¿Querés restablecer la contraseña de':disabling?'¿Querés deshabilitar a':'¿Querés eliminar definitivamente a';
        deleteDialog.querySelector('.modal-body .text-muted').textContent=resetting?'La nueva contraseña será el DNI registrado del alumno. Su usuario no cambiará.':disabling?'El usuario no podrá ingresar al sistema. Sus datos se conservarán.':'Se borrarán su cuenta y sus datos asociados.';
        deleteDialog.querySelector('.user-confirm-warning').textContent=resetting?'La contraseña anterior dejará de funcionar. El alumno podrá cambiarla desde Mi perfil.':disabling?'Podés volver a habilitarlo desde Usuarios cuando lo necesites.':'Esta acción es permanente y no se puede deshacer.';
        deleteConfirm.textContent=disabling?'Deshabilitar usuario':'Eliminar usuario';
        deleteConfirm.classList.toggle('btn-warning',disabling);
        deleteConfirm.classList.toggle('btn-danger',!disabling && !resetting);
        deleteConfirm.classList.toggle('btn-primary',resetting);
        deleteConfirm.disabled=false;
        deleteDialog.showModal();
    });
});
deleteDialog.querySelectorAll('[data-cancel-delete]').forEach(button=>{
    button.addEventListener('click',()=>deleteDialog.close());
});
deleteDialog.addEventListener('close',()=>{ pendingDelete=null; });
deleteConfirm.addEventListener('click',()=>{
    if(!pendingDelete)return;
    const form=pendingDelete;
    confirmedDelete=form;
    deleteConfirm.disabled=true;
    form.requestSubmit(form.querySelector('button[name="operacion"]'));
});
const roleSelect=document.querySelector('select[name="rol"]');
const hourSelect=document.getElementById('horario_habitual');
function updateScheduleField(){
    if(!hourSelect)return;
    const applies=!roleSelect || roleSelect.value==='alumno';
    if(roleSelect){
        const autoDni=applies;
        const credentials=document.getElementById('account-credentials');
        credentials.hidden=autoDni;
        credentials.querySelectorAll('input').forEach(input=>{input.disabled=autoDni;input.required=!autoDni;});
        document.getElementById('dni-access-note').hidden=!autoDni;
    }
    document.querySelectorAll('[data-student-field]').forEach(group=>{
        group.hidden=!applies;
        group.querySelectorAll('input').forEach(input=>{input.disabled=!applies;input.required=applies;});
    });
    document.querySelector('label[for="field-1"]').textContent=applies?'Nombre':'Nombre y apellido';
    document.getElementById('horario-cuenta').hidden=!applies;
    hourSelect.required=applies;
    hourSelect.disabled=!applies;
}
roleSelect?.addEventListener('change',updateScheduleField);
updateScheduleField();
const newUserDialog=document.getElementById('new-user-dialog');
if(newUserDialog){
    document.getElementById('open-new-user').addEventListener('click',()=>newUserDialog.showModal());
    newUserDialog.querySelectorAll('[data-close-new-user]').forEach(button=>button.addEventListener('click',()=>newUserDialog.close()));
    <?php if(!$edit && $error && ($_POST['operacion']??'')==='cuenta'): ?>
    newUserDialog.showModal();
    <?php endif; ?>
}
</script>
<?php page_footer();

