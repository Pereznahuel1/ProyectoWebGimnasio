<?php
if (!isset($module)) { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/domain.php';
require __DIR__ . '/layout.php';
$user=current_user();
if (!$user) redirect('login.php');
$isAdmin=$user['rol']==='administrador';
if (in_array($module,['usuarios','horarios'],true) && !$isAdmin) { http_response_code(403); exit('Acceso exclusivo del administrador.'); }
$studentId=$isAdmin?null:(int)(one('SELECT id FROM alumnos WHERE usuario_id=?',[$user['id']])['id']??0);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isAdmin) { http_response_code(403); exit('Solo el administrador puede modificar datos.'); }
    check_csrf();
    try {
        $_SESSION['flash']=run_action($module,$_POST,$user);
        if($module==='usuarios' && is_string($_GET['buscar']??null) && trim($_GET['buscar'])!=='')redirect('usuarios.php?'.http_build_query(['buscar'=>trim($_GET['buscar'])]));
        redirect($module.'.php'.($module==='rutinas'?'?dia='.(int)$_POST['dia']:''));
    } catch (InvalidArgumentException $e) { $error=$e->getMessage();http_response_code(422); }
    catch (PDOException $e) {
        error_log($e->getMessage());http_response_code(422);
        $error=($e->errorInfo[1]??0)===1062?'El usuario o DNI ya está registrado. Revisá los datos.':'No se pudieron guardar los cambios. Intentá nuevamente.';
    }
}
function form_start(string $op,array $hidden=[]): void {
    $GLOBALS["formOperation"]=$op;
    echo '<form method="post" class="row g-3">';
    foreach(array_merge(['csrf'=>$_SESSION['csrf'],'operacion'=>$op],$hidden) as $key=>$value)
        echo '<input type="hidden" name="'.escape($key).'" value="'.escape((string)$value).'">';
}
function input_field(string $name,string $label,string $type='text',string $value='',bool $required=true,string $extra=''): void {
    if ($type !== 'password' && !empty($GLOBALS['error']) && ($GLOBALS['formOperation']??null)===($_POST['operacion']??null) && is_string($_POST[$name]??null)) $value=$_POST[$name];
    static $seq=0;$id='field-'.++$seq;
    echo '<div class="col-md-6"><label class="form-label" for="'.$id.'">'.escape($label).'</label><input class="form-control" id="'.$id.'" name="'.escape($name).'" type="'.escape($type).'" value="'.escape($value).'" '.($required?'required ':'').$extra.'></div>';
}
function select_field(string $name,string $label,array $options,string $selected=''): void {
    if (!empty($GLOBALS['error']) && ($GLOBALS['formOperation']??null)===($_POST['operacion']??null) && is_string($_POST[$name]??null)) $selected=$_POST[$name];
    static $seq=0;$id='select-'.++$seq;
    echo '<div class="col-md-6"><label class="form-label" for="'.$id.'">'.escape($label).'</label><select class="form-select" id="'.$id.'" name="'.escape($name).'" required><option value="">Seleccionar</option>';
    foreach($options as $key=>$label) echo '<option value="'.escape((string)$key).'" '.((string)$key===$selected?'selected':'').'>'.escape($label).'</option>';
    echo '</select></div>';
}
function form_end(string $label='Guardar'): void { echo '<div class="col-12"><button class="btn btn-primary" type="submit">'.escape($label).'</button></div></form>'; }
function card_start(string $title): void { echo '<section class="card mb-4"><div class="card-header">'.escape($title).'</div><div class="card-body">'; }
function card_end(): void { echo '</div></section>'; }
function table_start(array $headers): void { echo '<div class="table-responsive"><table class="table align-middle"><thead><tr>';foreach($headers as $label)echo '<th scope="col">'.escape($label).'</th>';echo '</tr></thead><tbody>'; }
function table_end(): void { echo '</tbody></table></div>'; }
function cell(string $value): void { echo '<td>'.escape($value).'</td>'; }
function date_label(string $date): string { return (new DateTimeImmutable($date))->format('d/m/Y'); }
function active_student_options(): array { $out=[];foreach(rows("SELECT a.id,u.nombre,u.usuario FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.activo=1 AND u.rol='alumno' ORDER BY u.nombre") as $row)$out[$row['id']]=$row['nombre'].' ('.$row['usuario'].')';return $out; }
function query_id(string $name): int { $id=filter_var($_GET[$name]??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $id?:0; }
function page_intro(string $title): void {
    global $user,$module,$error;
    page_header($user,$title,$module);
    echo '<h1 class="mb-2">'.escape($title).'</h1><ol class="breadcrumb mb-4"><li class="breadcrumb-item"><a href="index.php">Inicio</a></li><li class="breadcrumb-item active">'.escape($title).'</li></ol>';
    if($error)echo '<div class="alert alert-danger" role="alert">'.escape($error).'</div>';
    if(isset($_SESSION['flash'])) {echo '<div class="alert alert-success" role="status">'.escape($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}
}

