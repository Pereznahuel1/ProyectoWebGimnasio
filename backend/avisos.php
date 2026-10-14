<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/domain.php';require __DIR__.'/layout.php';
$user=require_role('administrador');
$edit=filter_var($_GET['editar']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$notice=$edit?one('SELECT id,contenido,formato_html,(imagen IS NOT NULL) tiene_imagen FROM avisos WHERE id=?',[$edit]):null;
if($edit && !$notice){http_response_code(404);exit('Aviso no encontrado.');}
$content=$notice?notice_html($notice):'';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 try{
  $op=field($_POST,'operacion',20);
  if($op==='eliminar'){
   $id=number($_POST,'id');execute_sql('DELETE FROM avisos WHERE id=?',[$id]);

   $_SESSION['notice_flash']='Aviso eliminado.';redirect('avisos.php');
  }
  if($op!=='guardar')throw new InvalidArgumentException('Operación inválida.');
  $content=is_string($_POST['contenido']??null)?str_replace(["\r\n","\r"],"\n",$_POST['contenido']):'';
  $content=($_POST['formato_html']??'')==='1'?clean_notice_html($content):nl2br(escape($content));
  if(mb_strlen($content)>20000)throw new InvalidArgumentException('El aviso admite hasta 20.000 caracteres.');
  $remove=($_POST['quitar_imagen']??'')==='1';$image=null;$mime=null;
  $file=$_FILES['imagen']??null;
  if($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
   if(!is_int($file['error']) || $file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('No se pudo adjuntar la imagen. Revisá el límite de carga del servidor.');
   if(!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Imagen inválida.');
   $info=@getimagesize($file['tmp_name']);
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
   if(!$info || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || ($info['mime']??'')!==$mime)throw new InvalidArgumentException('Adjuntá una imagen JPG, PNG o WebP.');
   $image=file_get_contents($file['tmp_name']);
  }
  db()->beginTransaction();
  $existing=$edit?one('SELECT id,(imagen IS NOT NULL) tiene_imagen FROM avisos WHERE id=? FOR UPDATE',[$edit]):null;
  if($edit && !$existing)throw new InvalidArgumentException('El aviso ya no existe.');
  $hasImage=$image!==null || (!$remove && !empty($existing['tiene_imagen']));
  if(trim($content)==='' && !$hasImage)throw new InvalidArgumentException('Escribí un aviso o adjuntá una imagen.');
  $now=date('Y-m-d H:i:s');
  if(!$edit){execute_sql('INSERT INTO avisos(contenido,imagen_tipo,creado_en,actualizado_en,formato_html) VALUES(?,?,?,?,1)',[$content,$mime,$now,$now]);$savedId=(int)db()->lastInsertId();}
  else{
   execute_sql('UPDATE avisos SET contenido=?,actualizado_en=?,formato_html=1 WHERE id=?',[$content,$now,$edit]);
   $savedId=$edit;
  }
  if($image!==null || $remove){
   execute_sql('UPDATE avisos SET imagen=?,imagen_tipo=? WHERE id=?',[$image!==null?'':null,$mime,$savedId]);
   if($image!==null)foreach(str_split($image,262144) as $chunk)execute_sql('UPDATE avisos SET imagen=CONCAT(imagen,?) WHERE id=?',[$chunk,$savedId]);
  }
  db()->commit();$_SESSION['notice_flash']=$edit?'Aviso actualizado.':'Aviso publicado para todos los alumnos.';redirect('avisos.php');
 }catch(InvalidArgumentException|PDOException $e){
  if(db()->inTransaction())db()->rollBack();http_response_code(422);
  $error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar el aviso. Intentá nuevamente.';
 }
}
$notices=rows('SELECT id,contenido,formato_html,creado_en,(imagen IS NOT NULL) tiene_imagen FROM avisos ORDER BY creado_en DESC,id DESC');
page_header($user,'Avisos','avisos');
?>
<h1 class="mb-4">Avisos</h1>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if(isset($_SESSION['notice_flash'])): ?><div class="alert alert-success" role="status"><?= escape($_SESSION['notice_flash']) ?></div><?php unset($_SESSION['notice_flash']);endif; ?>
<div class="card mb-4"><div class="card-header"><?= $edit?'Editar aviso':'Nuevo aviso' ?></div><div class="card-body">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="guardar">
<label id="notice-label" class="form-label">Escribí el aviso</label>
<div class="d-flex flex-wrap gap-2 p-2 border rounded-top bg-light" role="toolbar" aria-label="Formato del aviso">
<button type="button" class="btn btn-sm btn-outline-secondary" data-command="bold" aria-label="Negrita"><strong>Negrita</strong></button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-command="italic" aria-label="Cursiva"><em>Cursiva</em></button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-block="h2">Título</button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-block="h3">Subtítulo</button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-block="p">Texto</button>
</div>
<div class="form-control notice-rich-editor mb-3" id="notice-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="notice-label"><?= clean_notice_html($content) ?></div>
<input type="hidden" name="contenido" id="notice-content"><input type="hidden" name="formato_html" value="1">
<label for="notice-image" class="form-label">Adjuntar imagen (opcional)</label>
<input class="form-control" type="file" name="imagen" id="notice-image" accept="image/jpeg,image/png,image/webp">
<div class="mb-3"></div>
<?php if(!empty($notice['tiene_imagen'])): ?><img class="notice-image mb-3" src="aviso-imagen.php?id=<?= $edit ?>" alt="Imagen actual del aviso"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="remove-image" name="quitar_imagen" value="1"><label class="form-check-label" for="remove-image">Quitar imagen actual</label></div><?php endif; ?>
<div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><?= $edit?'Guardar cambios':'Publicar aviso' ?></button><?php if($edit): ?><a class="btn btn-outline-secondary" href="avisos.php">Cancelar</a><?php endif; ?></div>
</form></div></div>
<h2 class="h4 mb-3">Avisos publicados</h2>
<?php if(!$notices): ?><p class="text-muted">Todavía no hay avisos publicados.</p><?php endif; ?>
<?php foreach($notices as $item): ?>
<article class="card mb-4"><div class="card-header"><?= escape(date('d/m/Y H:i',strtotime($item['creado_en']))) ?></div><div class="card-body">
<?php if($item['tiene_imagen']): ?><img class="notice-image mb-3" src="aviso-imagen.php?id=<?= (int)$item['id'] ?>" alt="Imagen del aviso" loading="lazy"><?php endif; ?>
<div class="notice-text mb-3"><?= notice_html($item) ?></div>
<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="avisos.php?editar=<?= (int)$item['id'] ?>">Editar</a>
<form method="post" action="avisos.php" data-delete-notice><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="operacion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button></form></div>
</div></article>
<?php endforeach; ?>
<dialog id="notice-dialog" class="user-confirm-dialog" aria-labelledby="notice-dialog-title"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="notice-dialog-title">Eliminar aviso</h2><button class="btn-close" type="button" data-cancel aria-label="Cerrar"></button></div><div class="modal-body">El aviso y su imagen dejarán de aparecer en el dashboard de los alumnos.</div><div class="modal-footer"><button class="btn btn-light" type="button" data-cancel autofocus>Cancelar</button><button class="btn btn-danger" type="button" id="confirm-notice">Eliminar aviso</button></div></div></dialog>
<script>
const editor=document.getElementById('notice-editor');
const contentField=document.getElementById('notice-content');
let selectionRange=null;
document.addEventListener('selectionchange',()=>{const selection=window.getSelection();if(selection.rangeCount && editor.contains(selection.anchorNode) && editor.contains(selection.focusNode))selectionRange=selection.getRangeAt(0).cloneRange();});
document.querySelectorAll('[data-command], [data-block]').forEach(button=>{
 button.addEventListener('mousedown',event=>event.preventDefault());
 button.addEventListener('click',()=>{editor.focus();if(selectionRange){const selection=window.getSelection();selection.removeAllRanges();selection.addRange(selectionRange);}document.execCommand(button.dataset.command||'formatBlock',false,button.dataset.block||null);});
});
editor.addEventListener('paste',event=>{event.preventDefault();document.execCommand('insertText',false,event.clipboardData.getData('text/plain'));});
editor.addEventListener('drop',event=>event.preventDefault());
contentField.form.addEventListener('submit',()=>{contentField.value=editor.innerHTML;});

const dialog=document.getElementById('notice-dialog');let pending=null;
document.querySelectorAll('[data-delete-notice]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();pending=form;dialog.showModal();}));
dialog.querySelectorAll('[data-cancel]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
dialog.addEventListener('close',()=>pending=null);
document.getElementById('confirm-notice').addEventListener('click',event=>{if(pending){event.currentTarget.disabled=true;pending.submit();}});
</script>
<?php page_footer(); ?>