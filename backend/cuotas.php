<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/domain.php';
require __DIR__.'/layout.php';
$user=current_user();
if(!$user) redirect('login.php');
$isAdmin=$user['rol']==='administrador';
$error='';
$search=$isAdmin && is_string($_GET['buscar']??null)?trim($_GET['buscar']):'';
$state=$isAdmin && is_string($_GET['estado']??null)?$_GET['estado']:'';
if(!in_array($state,['al_dia','vencida'],true))$state='';
$filterQuery=http_build_query(array_filter(['buscar'=>$search,'estado'=>$state],static fn($value)=>$value!==''));
$returnUrl='cuotas.php'.($filterQuery!==''?'?'.$filterQuery:'');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){http_response_code(403);exit('Solo el administrador puede registrar cuotas.');}
    check_csrf();
    try{
        $operation=$_POST['operacion']??'';
        if(!in_array($operation,['marcar_al_dia','marcar_vencida'],true))throw new InvalidArgumentException('Operación inválida.');
        $id=number($_POST,'alumno_id');
        db()->beginTransaction();
        $student=one("SELECT a.id FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.id=? AND u.rol='alumno' FOR UPDATE",[$id]);
        if(!$student)throw new InvalidArgumentException('Alumno no encontrado.');
        if($operation==='marcar_vencida'){
            execute_sql('UPDATE alumnos SET ultimo_mes_abonado=NULL WHERE id=?',[$id]);
        }else{
        $month=date('Y-m-01');
        execute_sql('UPDATE alumnos SET ultimo_mes_abonado=CASE WHEN ultimo_mes_abonado IS NULL OR ultimo_mes_abonado<? THEN ? ELSE ultimo_mes_abonado END WHERE id=?',[$month,$month,$id]);
        }
        db()->commit();
        $_SESSION['cuota_flash']=$operation==='marcar_vencida'?'La cuota se marcó como vencida.':'Cuota registrada. El alumno está al día.';
        redirect($returnUrl);
    }catch(InvalidArgumentException|PDOException $e){
        if(db()->inTransaction())db()->rollBack();
        http_response_code(422);
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo registrar la cuota. Intentá nuevamente.';
    }
}elseif(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){
    header('Allow: GET, HEAD, POST');http_response_code(405);exit;
}
$sql="SELECT a.id,a.dni,a.ultimo_mes_abonado,u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.rol='alumno'";
$params=[];
if(!$isAdmin){$sql.=' AND a.usuario_id=?';$params[]=$user['id'];}
if($isAdmin && $search!==''){
    $sql.=' AND (LOCATE(?,u.nombre)>0 OR LOCATE(?,a.dni)>0)';
    $params[]=$search;$params[]=$search;
}
if($isAdmin && $state!==''){
    $sql.=$state==='al_dia'?' AND a.ultimo_mes_abonado>=?':' AND (a.ultimo_mes_abonado IS NULL OR a.ultimo_mes_abonado<?)';
    $params[]=cuota_periodo_exigible();
}
$students=rows($sql.' ORDER BY u.nombre COLLATE utf8mb4_spanish_ci ASC,u.usuario ASC,a.id ASC',$params);
page_header($user,$isAdmin?'Cuotas':'Mi cuota','cuotas');
?>
<h1 class="mb-4"><?= $isAdmin?'Cuotas':'Mi cuota' ?></h1>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if(isset($_SESSION['cuota_flash'])): ?><div class="alert alert-success" role="status"><?= escape($_SESSION['cuota_flash']) ?></div><?php unset($_SESSION['cuota_flash']);endif; ?>

<?php if($isAdmin): ?>
<form method="get" action="cuotas.php" class="row g-3 align-items-end mb-4">
<div class="col-md-6"><label for="buscar-cuota" class="form-label">Filtrar por nombre o DNI</label><input class="form-control" type="search" id="buscar-cuota" name="buscar" value="<?= escape($search) ?>" placeholder="Nombre, apellido o DNI"></div>
<div class="col-md-3"><label for="estado-cuota" class="form-label">Estado de la cuota</label><select class="form-select" id="estado-cuota" name="estado"><option value="">Todos</option><option value="al_dia" <?= $state==='al_dia'?'selected':'' ?>>Al día</option><option value="vencida" <?= $state==='vencida'?'selected':'' ?>>Vencida</option></select></div>
<div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Buscar</button><a class="btn btn-outline-secondary" href="cuotas.php">Limpiar</a></div>
</form>
<?php endif; ?>
<div class="card mb-4"><div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th scope="col">Alumno</th><th scope="col">DNI</th><th scope="col">Estado de la cuota</th><?php if($isAdmin): ?><th scope="col">Acciones</th><?php endif; ?></tr></thead>
<tbody>
<?php foreach($students as $student): $current=cuota_al_dia($student['ultimo_mes_abonado']); ?>
<tr><td><?= escape($student['nombre']) ?></td><td><?= escape($student['dni']??'Sin registrar') ?></td><td><span class="badge <?= $current?'bg-success':'bg-danger' ?>"><?= $current?'Al día':'Vencida' ?></span></td>
<?php if($isAdmin): ?><td><div class="d-flex flex-wrap align-items-center gap-2">
<?php if(($student['ultimo_mes_abonado']??'')>=date('Y-m-01')): ?>
<span class="text-muted small">Mes actual abonado</span>
<?php else: ?>
<form method="post" action="<?= escape($returnUrl) ?>" class="m-0">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<input type="hidden" name="operacion" value="marcar_al_dia">
<input type="hidden" name="alumno_id" value="<?= (int)$student['id'] ?>">
<button class="btn btn-sm btn-outline-success" type="submit" aria-label="Marcar al día a <?= escape($student['nombre']) ?>">Marcar al día</button>
</form>
<?php endif; ?>
<?php if($current): ?>
<form method="post" action="<?= escape($returnUrl) ?>" class="m-0">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<input type="hidden" name="operacion" value="marcar_vencida">
<input type="hidden" name="alumno_id" value="<?= (int)$student['id'] ?>">
<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Marcar vencida la cuota de <?= escape($student['nombre']) ?>">Marcar vencida</button>
</form>
<?php endif; ?></div></td><?php endif; ?></tr>
<?php endforeach; ?>
<?php if(!$students): ?><tr><td colspan="<?= $isAdmin?4:3 ?>" class="text-center text-muted py-4">No hay alumnos que coincidan con la búsqueda.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php page_footer(); ?>
