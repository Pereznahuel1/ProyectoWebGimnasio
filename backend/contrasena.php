<?php
require __DIR__.'/bootstrap.php';
$user=require_role('alumno');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    try{
        $current=is_string($_POST['actual']??null)?$_POST['actual']:'';
        $password=is_string($_POST['nueva']??null)?$_POST['nueva']:'';
        $repeat=is_string($_POST['repetir']??null)?$_POST['repetir']:'';
        if($password==='')throw new InvalidArgumentException('Ingresá la nueva contraseña.');
        if($password!==$repeat)throw new InvalidArgumentException('Las contraseñas nuevas no coinciden.');
        db()->beginTransaction();
        $query=db()->prepare('SELECT password_hash FROM usuarios WHERE id=? AND activo=1 FOR UPDATE');
        $query->execute([$user['id']]);$account=$query->fetch();
        if(!$account || !password_verify($current,$account['password_hash']))throw new InvalidArgumentException('La contraseña actual no es correcta.');
        $query=db()->prepare('UPDATE usuarios SET password_hash=? WHERE id=?');
        $query->execute([hash_password($password),$user['id']]);
        db()->commit();
        session_regenerate_id(true);
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['password_changed']=true;
        redirect('contrasena.php');
    }catch(InvalidArgumentException|PDOException $e){
        if(db()->inTransaction())db()->rollBack();
        http_response_code(422);
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar la contraseña. Intentá nuevamente.';
    }
}
require __DIR__.'/layout.php';
page_header($user,'Cambiar contraseña','perfil');
?>
<h1 class="mb-4">Cambiar contraseña</h1>
<div class="row"><div class="col-lg-6"><div class="card mb-4"><div class="card-header">Mi acceso</div><div class="card-body">
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if(!empty($_SESSION['password_changed'])): unset($_SESSION['password_changed']); ?><div class="alert alert-success" role="status">Tu contraseña se actualizó correctamente.</div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<div class="mb-3"><label for="actual" class="form-label">Contraseña actual</label><input class="form-control" type="password" id="actual" name="actual" autocomplete="current-password" required></div>
<div class="mb-3"><label for="nueva" class="form-label">Nueva contraseña</label><input class="form-control" type="password" id="nueva" name="nueva" autocomplete="new-password" required></div>
<div class="mb-4"><label for="repetir" class="form-label">Repetir nueva contraseña</label><input class="form-control" type="password" id="repetir" name="repetir" autocomplete="new-password" required></div>
<button class="btn btn-primary" type="submit">Guardar contraseña</button> <a class="btn btn-outline-secondary" href="perfil.php">Volver a mi perfil</a>
</form></div></div></div></div>
<?php page_footer(); ?>