<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/domain.php';
$user = require_role('administrador');
function text_input(array $data, string $key): string {
    return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
}
function student(int $id, bool $lock = false): ?array {
    $q = db()->prepare("SELECT a.*, u.nombre, u.usuario, u.activo FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.id=? AND u.rol='alumno'" . ($lock ? ' FOR UPDATE' : ''));
    $q->execute([$id]);
    return $q->fetch() ?: null;
}
$action = text_input($_GET, 'accion') ?: 'listar';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
if (!in_array($action, ['listar','nuevo','editar'], true)) { http_response_code(404); exit('Página no encontrada.'); }
$values = ['nombre'=>'','apellido'=>'','fecha_nacimiento'=>'','dni'=>'','correo'=>'','usuario'=>'','telefono'=>'','ultimo_mes_abonado'=>'','horario_habitual'=>''];
if ($action === 'editar') {
    $values = student($id);
    if (!$values) { http_response_code(404); exit('Alumno no encontrado.'); }
    $values['nombre']=$values['nombres']??$values['nombre'];
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $op = text_input($_POST, 'operacion');
        if ($op === 'estado' && $action === 'listar') {
            $target = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
            $state = text_input($_POST, 'activo');
            if (!in_array($state, ['0','1'], true)) { throw new InvalidArgumentException('Estado inválido.'); }
            db()->beginTransaction();
            bloquear_horarios();
            $record = student($target, true);
            if (!$record) { throw new InvalidArgumentException('Alumno no encontrado.'); }
            if($state==='1') {
                if(!$record['horario_habitual']) throw new InvalidArgumentException('Asigná primero un horario desde Editar.');
                comprobar_cupo($record['horario_habitual'],(int)$record['id']);
            }
            $q = db()->prepare("UPDATE usuarios SET activo=? WHERE id=? AND rol='alumno'");
            $q->execute([(int)$state, $record['usuario_id']]);
            db()->commit();
            $_SESSION['flash'] = $state === '1' ? 'Alumno reactivado.' : 'Alumno desactivado. Ya no puede ingresar.';
            redirect('alumnos.php');
        }
        if ($op !== 'guardar' || !in_array($action, ['nuevo','editar'], true)) {
            throw new InvalidArgumentException('Operación inválida.');
        }
        $values = ['nombre'=>text_input($_POST,'nombre'), 'usuario'=>text_input($_POST,'usuario'), 'telefono'=>text_input($_POST,'telefono'),'ultimo_mes_abonado'=>text_input($_POST,'ultimo_mes_abonado'),'horario_habitual'=>text_input($_POST,'horario_habitual')];
        foreach(['apellido','fecha_nacimiento','dni','correo'] as $key) $values[$key]=text_input($_POST,$key);
        $details=student_details($values);
        if($action==='nuevo') $values['usuario']=$details[3];
        $hour=validar_horario($values['horario_habitual']);
        if(isset($_POST['ultimo_mes_abonado']) && !is_string($_POST['ultimo_mes_abonado'])) throw new InvalidArgumentException('Mes abonado inválido.');
        $paidMonth=validar_mes_abonado($values['ultimo_mes_abonado']);
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($values['nombre'] === '' || mb_strlen($values['nombre'],'UTF-8') > 120) { throw new InvalidArgumentException('Ingresá un nombre de hasta 120 caracteres.'); }
        if (!valid_username($values['usuario'])) { throw new InvalidArgumentException('El usuario debe tener de 3 a 50 letras sin tildes, números, puntos, guiones o guiones bajos.'); }
        if (mb_strlen($values['telefono'],'UTF-8') > 40) { throw new InvalidArgumentException('El teléfono admite hasta 40 caracteres.'); }
        if($action==='nuevo') $password=$details[3];
        db()->beginTransaction();
            bloquear_horarios();
        if ($action === 'nuevo') {
            comprobar_cupo($hour);
            $q = db()->prepare("INSERT INTO usuarios (nombre,usuario,password_hash,rol) VALUES (?,?,?,'alumno')");
            $q->execute([$values['nombre'],$values['usuario'],hash_password($password)]);
            $userId = (int)db()->lastInsertId();
            $q = db()->prepare('INSERT INTO alumnos (usuario_id,telefono,fecha_alta,ultimo_mes_abonado,horario_habitual) VALUES (?,?,?,?,?)');
            $q->execute([$userId,$values['telefono'] ?: null,(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d'),$paidMonth,$hour]);
        } else {
            $record = student($id,true);
            if (!$record) { throw new InvalidArgumentException('Alumno no encontrado.'); }
            if($record['activo']) comprobar_cupo($hour,(int)$record['id']);
            $q = db()->prepare("UPDATE usuarios SET nombre=?,usuario=? WHERE id=? AND rol='alumno'");
            $q->execute([$values['nombre'],$values['usuario'],$record['usuario_id']]);
            if ($password !== '') {
                $q = db()->prepare("UPDATE usuarios SET password_hash=? WHERE id=? AND rol='alumno'");
                $q->execute([hash_password($password),$record['usuario_id']]);
            }
            $q = db()->prepare('UPDATE alumnos SET telefono=?,ultimo_mes_abonado=?,horario_habitual=? WHERE id=?');
            $q->execute([$values['telefono'] ?: null,$paidMonth,$hour,$id]);
        }
        save_student_details($action==='nuevo'?$userId:(int)$record['usuario_id'],$details);
        db()->commit();
        $_SESSION['flash'] = $action === 'nuevo' ? 'Alumno y cuenta creados.' : 'Datos del alumno actualizados.';
        redirect('alumnos.php');
    } catch (InvalidArgumentException | PDOException $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        if ($e instanceof PDOException) {
            error_log($e->getMessage());
            $error = ($e->errorInfo[1] ?? null) === 1062 ? 'El usuario o DNI ya está registrado. Revisá los datos.' : 'No se pudieron guardar los cambios. Intentá nuevamente.';
        } else { $error = $e->getMessage(); }
        http_response_code(422);
    }
}
$search = text_input($_GET,'buscar');
$state = text_input($_GET,'estado');
$students = [];
if ($action === 'listar') {
    $sql = "SELECT a.*,u.nombre,u.usuario,u.activo FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.rol='alumno'";
    $params = [];
    if ($search !== '') { $sql .= ' AND (LOCATE(?,u.nombre)>0 OR LOCATE(?,a.dni)>0)'; $params=array_merge($params,[$search,$search]); }
    if (in_array($state,['0','1'],true)) { $sql .= ' AND u.activo=?'; $params[]=(int)$state; }
    $q = db()->prepare($sql . ' ORDER BY u.nombre,a.id');
    $q->execute($params);
    $students = $q->fetchAll();
}
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$title = match($action) { 'nuevo'=>'Nuevo alumno', 'editar'=>'Editar alumno', default=>'Alumnos' };
?>
<?php
require_once __DIR__ . '/layout.php';
page_header($user, $title, 'alumnos');
?>
<a href="<?= $action==='listar' ? 'admin.php' : 'alumnos.php' ?>">Volver <?= $action==='listar' ? 'al panel' : 'a alumnos' ?></a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-3"><h1><?= escape($title) ?></h1>
<?php if ($action==='listar'): ?><a class="btn btn-primary" href="alumnos.php?accion=nuevo">Nuevo alumno</a><?php endif; ?></div>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= escape($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if ($action==='listar'): ?>
<form method="get" class="row g-3 align-items-end mb-4">
<div class="col-md-6"><label for="buscar" class="form-label">Buscar por nombre, apellido o DNI</label><input id="buscar" class="form-control" name="buscar" value="<?= escape($search) ?>"></div>
<div class="col-md-3"><label for="estado" class="form-label">Estado</label><select id="estado" class="form-select" name="estado"><option value="">Todos</option><option value="1" <?= $state==='1'?'selected':'' ?>>Activos</option><option value="0" <?= $state==='0'?'selected':'' ?>>Inactivos</option></select></div>
<div class="col-md-3"><button class="btn btn-primary">Buscar</button> <a href="alumnos.php" class="btn btn-outline-secondary">Limpiar</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<caption class="px-3"><?= count($students) ?> alumno(s)</caption>
<thead><tr><th scope="col">Nombre</th><th scope="col">DNI</th><th scope="col">Teléfono</th><th scope="col">Alta</th><th scope="col">Estado</th><th scope="col">Horario habitual</th><th scope="col">Cuota</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($students as $s): ?>
<tr><td><?= escape($s['nombre']) ?></td><td><?= escape($s['dni'] ?? 'Sin registrar') ?></td><td><?= escape($s['telefono'] ?? 'Sin teléfono') ?></td><td><?= escape(date('d/m/Y',strtotime($s['fecha_alta']))) ?></td><td><span class="badge <?= $s['activo']?'bg-success':'bg-secondary' ?>"><?= $s['activo']?'Activo':'Inactivo' ?></span></td>
<td><?= escape($s['horario_habitual'] ?? 'Sin asignar') ?></td>
<td><?= cuota_al_dia($s['ultimo_mes_abonado']) ? 'Al día' : 'Vencida' ?></td>
<td><div class="d-flex flex-wrap gap-2">
<a class="btn btn-sm btn-outline-primary" href="alumnos.php?accion=editar&amp;id=<?= (int)$s['id'] ?>" aria-label="Editar a <?= escape($s['nombre']) ?>">Editar</a>
<form method="post" action="alumnos.php" class="student-state-form">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="estado"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="activo" value="<?= $s['activo']?'0':'1' ?>">
<button class="btn btn-sm <?= $s['activo']?'btn-outline-danger':'btn-outline-success' ?>" aria-label="<?= $s['activo']?'Desactivar a ':'Reactivar a ' ?><?= escape($s['nombre']) ?>"><?= $s['activo']?'Desactivar':'Reactivar' ?></button>
</form></div></td></tr>
<?php endforeach; ?>
<?php if (!$students): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay alumnos para mostrar.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php else: ?>
<div class="card"><div class="card-body">
<form method="post" action="alumnos.php?accion=<?= escape($action) ?><?= $action==='editar'?'&amp;id='.$id:'' ?>">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="guardar">
<div class="row g-3">
<div class="col-md-6"><label for="nombre" class="form-label">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="120" autocomplete="given-name" value="<?= escape($values['nombre']) ?>"></div>
<div class="col-md-6"><label for="apellido" class="form-label">Apellido</label><input class="form-control" id="apellido" name="apellido" type="text" required maxlength="59" autocomplete="family-name" value="<?= escape($values['apellido'] ?? '') ?>"></div>
<div class="col-md-6"><label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label><input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date" required min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= escape($values['fecha_nacimiento'] ?? '') ?>"></div>
<div class="col-md-6"><label for="dni" class="form-label">DNI</label><input class="form-control" id="dni" name="dni" type="text" required inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" value="<?= escape($values['dni'] ?? '') ?>"></div>
<div class="col-md-6"><label for="correo" class="form-label">Correo electrónico</label><input class="form-control" id="correo" name="correo" type="email" required maxlength="190" autocomplete="email" value="<?= escape($values['correo'] ?? '') ?>"></div>
<?php if($action==='editar'): ?>
<div class="col-md-6"><label for="usuario" class="form-label">Usuario</label><input class="form-control" id="usuario" name="usuario" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}" autocapitalize="none" spellcheck="false" aria-describedby="usuario-ayuda" value="<?= escape($values['usuario']) ?>"><div id="usuario-ayuda" class="form-text">De 3 a 50 letras sin tildes, números, puntos, guiones o guiones bajos.</div></div>
<?php endif; ?>
<div class="col-md-6"><label for="telefono" class="form-label">Teléfono</label><input class="form-control" type="tel" required id="telefono" name="telefono" maxlength="40" autocomplete="tel" value="<?= escape($values['telefono'] ?? '') ?>"></div>
<div class="col-md-6"><label for="horario_habitual" class="form-label">Horario habitual</label><select class="form-select" id="horario_habitual" name="horario_habitual" required><option value="">Seleccionar horario</option><?php foreach(horarios_gimnasio() as $hourOption): ?><option value="<?= escape($hourOption) ?>" <?= ($values['horario_habitual']??'')===$hourOption?'selected':'' ?>><?= escape($hourOption) ?></option><?php endforeach; ?></select><div class="form-text">Los cupos se configuran en Horarios.</div></div>
<div class="col-md-6"><label for="ultimo_mes_abonado" class="form-label">Último mes abonado</label><input class="form-control" id="ultimo_mes_abonado" name="ultimo_mes_abonado" type="month" min="2000-01" max="2100-12" value="<?= escape(substr($values['ultimo_mes_abonado'] ?? '',0,7)) ?>" aria-describedby="cuota-ayuda"><div class="form-text" id="cuota-ayuda">Vence el día 10 inclusive. Desde el 11 se exige el mes actual. Vacío indica que no hay un mes abonado registrado.</div></div>
<?php if($action==='editar'): ?>
<div class="col-md-6"><label for="password" class="form-label"><?= $action==='nuevo'?'Contraseña':'Nueva contraseña (opcional)' ?></label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" aria-describedby="password-ayuda" <?= $action==='nuevo'?'required':'' ?>><div id="password-ayuda" class="form-text"><?= $action==='editar'?'Dejala vacía para conservar la actual.':'El alumno ingresará con este usuario y contraseña.' ?></div></div>
<?php else: ?><div class="col-12"><div class="alert alert-info mb-0">El DNI será el usuario y la contraseña inicial. El alumno podrá cambiar su contraseña desde su cuenta.</div></div><?php endif; ?>
</div><div class="d-flex gap-2 mt-4"><button class="btn btn-primary"><?= $action==='nuevo'?'Crear alumno':'Guardar cambios' ?></button><a class="btn btn-outline-secondary" href="alumnos.php">Cancelar</a></div>
</form></div></div>
<?php endif; ?>

<script>
document.querySelectorAll('.student-state-form').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.elements.activo.value === '0' && !confirm('¿Desactivar este alumno? Se conservará su historial, pero no podrá ingresar.')) event.preventDefault();
    });
});
</script>
<?php page_footer(); ?>

