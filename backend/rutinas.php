<?php
$module='rutinas';require __DIR__.'/module.php';
$days=dias_semana();$today=(int)date('N');$dates=fechas_rutina();
$selected=isset($_GET['dia'])?filter_var($_GET['dia'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>6]]):($today===7?1:$today);
if($selected===false){http_response_code(400);exit('Día inválido.');}
if($error){
    $postedDay=filter_var($_POST['dia']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>6]]);
    if($postedDay!==false)$selected=$postedDay;
}
$saved=one('SELECT contenido,actualizada_en FROM rutinas_semanales WHERE dia=?',[$selected]);
$content=$saved['contenido']??'';
$updated=$saved['actualizada_en']??null;
$draft=$error && is_string($_POST['contenido']??null)?$_POST['contenido']:'';
page_intro('Rutinas de la semana');
?>
<nav class="nav nav-pills gap-2 flex-wrap mb-4" aria-label="Días de la semana">
<?php foreach($days as $day=>$label): ?>
    <a class="nav-link <?= $selected===$day?'active':'border' ?>" href="rutinas.php?dia=<?= $day ?>" <?= $selected===$day?'aria-current="page"':'' ?>><span data-routine-day="<?= $day ?>" data-show-today title="<?= escape($dates[$day]->format('d/m/Y')) ?>"><?= escape($label.' '.$dates[$day]->format('j')) ?><?= $day===$today?' · Hoy':'' ?></span></a>
<?php endforeach; ?>
</nav>
<?php if($isAdmin): ?>
<div class="card mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Escribir rutina del <span data-routine-day="<?= $selected ?>" title="<?= escape($dates[$selected]->format('d/m/Y')) ?>"><?= escape($days[$selected].' '.$dates[$selected]->format('j')) ?></span></h2></div>
    <div class="card-body">
        <form method="post" action="rutinas.php?dia=<?= $selected ?>" id="routine-form">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="operacion" value="rutina_semanal">
            <input type="hidden" name="dia" value="<?= $selected ?>">
            <label for="routine-editor" class="form-label">Escribí la rutina del día</label>
            <textarea id="routine-editor" name="contenido" class="form-control routine-editor mb-3" rows="16" maxlength="20000"><?= escape($draft) ?></textarea>
            <button class="btn btn-primary" type="submit">Guardar rutina</button>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="card mb-4" id="saved-routine">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h5 mb-0"><?= $isAdmin?'Rutina':'Rutina' ?> del <span data-routine-day="<?= $selected ?>" title="<?= escape($dates[$selected]->format('d/m/Y')) ?>"><?= escape($days[$selected].' '.$dates[$selected]->format('j')) ?></span></h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if($updated): ?><span class="small text-muted">Actualizada: <?= escape(date('d/m/Y H:i',strtotime($updated))) ?></span><?php endif; ?>
            <?php if($isAdmin && $content!==''): ?><button class="btn btn-sm btn-outline-primary" id="edit-routine" type="button">Editar</button><?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if($content!==''): ?>
            <div class="routine-text" id="routine-content"><?= escape($content) ?></div>
        <?php else: ?>
            <p class="text-muted mb-0">Todavía no se cargó la rutina de este día.</p>
        <?php endif; ?>
    </div>
</div>
<?php if($isAdmin): ?>
<script>
const editor = document.getElementById('routine-editor');
const original = editor.value;
let submitted = false;
document.getElementById('routine-form').addEventListener('submit', () => { submitted = true; });
document.getElementById('edit-routine')?.addEventListener('click', () => {
    if (editor.value !== '' && !confirm('¿Reemplazar el texto sin guardar por la rutina guardada?')) return;
    editor.value = document.getElementById('routine-content').textContent;
    editor.focus();
    editor.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
window.addEventListener('beforeunload', event => {
    if (!submitted && editor.value !== original) {
        event.preventDefault();
        event.returnValue = '';
    }
});
</script>
<?php endif; ?>
<script src="js/routine-dates.js" data-server-time="<?= time()*1000 ?>"></script>
<?php page_footer(); ?>
