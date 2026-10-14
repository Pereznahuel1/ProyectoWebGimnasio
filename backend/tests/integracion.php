<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function ok(bool $value,string $message): void { if(!$value)throw new RuntimeException($message);echo "OK: ".$message."\n"; }
function client() {
    $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c;
}
function http($c,string $page,?array $data=null): array {
    curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);
    if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));
    $html=curl_exec($c);if($html===false)throw new RuntimeException(curl_error($c));
    return [curl_getinfo($c,CURLINFO_HTTP_CODE),$html];
}
function token($c,string $page): string {
    [$status,$html]=http($c,$page);
    if($status!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Formulario '.$page.' HTTP '.$status.' '.strip_tags($html));
    return $m[1];
}
function test_personal_data(string $username): array {
    $existing=one('SELECT a.dni FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.usuario=?',[$username]);
    do{$dni=(string)random_int(10000000,99999999);}while(one('SELECT id FROM alumnos WHERE dni=?',[$dni]));
    return ['apellido'=>'Prueba','fecha_nacimiento'=>'1995-06-15','dni'=>$existing['dni']??$dni,'telefono'=>'1123456789','correo'=>'prueba@example.com'];
}
function send($c,string $page,array $data,int $expected,string $label): string {
    if(isset($data['nombre'],$data['usuario'])){
        $personal=test_personal_data($data['usuario']);
        $data=array_replace($personal,$data);
        if($data['telefono']==='')$data['telefono']=$personal['telefono'];
    }
    [$status,$body]=http($c,$page,$data);
    ok($status===$expected,$label.' (HTTP '.$status.')');
    return $body;
}
$prefix='qa_'.bin2hex(random_bytes(6));
$password=bin2hex(random_bytes(12));
$students=[];$userIds=[];$routineBackup=null;$routineDay=null;$routineSaved=null;
try {
    execute_sql("INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,'administrador')",[$prefix,$prefix.'_admin',password_hash($password,PASSWORD_DEFAULT)]);
    $adminId=(int)db()->lastInsertId();$userIds[]=$adminId;
    $admin=client();
    send($admin,'login.php',['usuario'=>$prefix.'_admin','password'=>$password,'csrf'=>token($admin,'login.php')],302,'Login administrador');
    $csrf=token($admin,'usuarios.php');
    foreach(['usuarios.php','cuotas.php','rutinas.php','clases.php','admin.php','alumnos.php'] as $page){
        [$status,$html]=http($admin,$page);ok($status===200&&!str_contains($html,'Warning:')&&!str_contains($html,'Fatal error:'),'Página '.$page);
    }
    $base=['nombre'=>$prefix.'_uno','usuario'=>$prefix.'_uno','password'=>$password,'rol'=>'alumno','activo'=>'1','horario_habitual'=>'07:00','csrf'=>$csrf];
    send($admin,'usuarios.php',array_replace($base,['csrf'=>'invalido']),403,'CSRF');
    send($admin,'usuarios.php',array_replace($base,['rol'=>'profesor']),422,'Solo dos roles');
    foreach(['uno','dos'] as $suffix){
        send($admin,'usuarios.php',array_replace($base,['nombre'=>$prefix.'_'.$suffix,'usuario'=>$prefix.'_'.$suffix]),302,'Crear alumno '.$suffix);
        $u=one('SELECT a.id,u.id uid FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.usuario=?',[$prefix.'_'.$suffix]);
        $students[]=(int)$u['id'];$userIds[]=(int)$u['uid'];
    }
    send($admin,'usuarios.php',$base,422,'Usuario duplicado');
    send($admin,'usuarios.php',['id'=>$adminId,'nombre'=>$prefix,'usuario'=>$prefix.'_admin','password'=>'','activo'=>'0','csrf'=>$csrf],422,'No desactivar administrador propio');
    $student=client();
    send($student,'login.php',['usuario'=>$prefix.'_uno','password'=>$password,'csrf'=>token($student,'login.php')],302,'Login alumno');
    ok(http($student,'usuarios.php')[0]===403,'Usuarios restringido');
    $studentCsrf=token($student,'cuotas.php');
    foreach(['cuotas.php','rutinas.php','clases.php'] as $page)send($student,$page,['csrf'=>$studentCsrf],in_array($page,['cuotas.php','clases.php'],true)?405:403,'Alumno no modifica '.$page);

    ok(cuota_al_dia('2026-09-01','2026-10-10'),'Día 10 inclusive: mes anterior cubierto');
    ok(!cuota_al_dia('2026-09-01','2026-10-11'),'Día 11: cuota actual exigible');
    ok(cuota_al_dia('2026-10-01','2026-10-11'),'Mes actual abonado');
    ok(!cuota_al_dia('2026-08-01','2026-10-01'),'Deuda anterior sigue vencida antes del 10');
    ok(cuota_al_dia('2025-12-01','2026-01-10')&&!cuota_al_dia('2025-12-01','2026-01-11'),'Cambio de año');
    ok(!cuota_al_dia(null,'2026-10-01'),'Sin pago registrado: vencida');
    $paidName='Álvaro '.$prefix;$lateName='Zoe '.$prefix;
    $studentEdit=['operacion'=>'guardar','nombre'=>$paidName,'usuario'=>$prefix.'_uno','telefono'=>'','password'=>'','ultimo_mes_abonado'=>date('Y-m'),'horario_habitual'=>'07:00','csrf'=>$csrf];
    send($admin,'alumnos.php?accion=editar&id='.$students[0],$studentEdit,302,'Registrar último mes abonado');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],array_replace($studentEdit,['ultimo_mes_abonado'=>'2026-13']),422,'Mes inválido rechazado');
    ok(one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$students[0]])['ultimo_mes_abonado']===date('Y-m-01'),'Mes guardado sin sobrescribir con dato inválido');
    send($admin,'alumnos.php?accion=editar&id='.$students[1],array_replace($studentEdit,['nombre'=>$lateName,'usuario'=>$prefix.'_dos','ultimo_mes_abonado'=>'']),302,'Alumno sin cuota abonada');
    [$status,$html]=http($admin,'cuotas.php');
    ok($status===200&&strpos($html,$paidName)<strpos($html,$lateName),'Cuotas ordenadas A a Z con acentos');
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new DOMXPath($dom);
    ok($xp->query('//main//thead/tr/th')->length===2,'Solo columnas Alumno y Estado');
    ok($xp->query('//main//form')->length===0,'Cuotas sin formularios');
    $statuses=[];foreach($xp->query('//main//tbody/tr') as $tr){$td=$tr->getElementsByTagName('td');if($td->length===2)$statuses[trim($td->item(0)->textContent)]=trim($td->item(1)->textContent);}
    ok(($statuses[$paidName]??'')==='Al día'&&($statuses[$lateName]??'')==='Vencida','Estados de cuota correctos');
    send($admin,'cuotas.php',['csrf'=>$csrf],405,'Cuotas de solo consulta');
    [$status,$html]=http($student,'cuotas.php?alumno_id='.$students[1]);
    ok($status===200&&str_contains($html,$paidName)&&!str_contains($html,$lateName),'Alumno solo ve su cuota');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],array_replace($studentEdit,['ultimo_mes_abonado'=>'']),302,'Limpiar mes abonado');
    ok(one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$students[0]])['ultimo_mes_abonado']===null,'Mes vacío guardado como NULL');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],$studentEdit,302,'Restaurar mes de prueba');

    $routineBackup=one("SELECT * FROM rutinas_semanales WHERE contenido='' AND dia BETWEEN 1 AND 6 ORDER BY (dia=?) DESC,dia LIMIT 1",[(int)date('N')]);
    if(!$routineBackup)throw new RuntimeException('Para esta prueba se necesita un día sin rutina.');
    $routineDay=(int)$routineBackup['dia'];
    $otherDay=$routineDay===6?1:$routineDay+1;
    $otherBefore=one('SELECT * FROM rutinas_semanales WHERE dia=?',[$otherDay]);
    $routineSaved=$prefix."\nEntrada en calor\n\n3 series de 12\n<script>alert(1)</script>\n";
    $routineForm=['operacion'=>'rutina_semanal','dia'=>$routineDay,'contenido'=>$routineSaved,'csrf'=>$csrf];
    send($admin,'rutinas.php?dia='.$routineDay,$routineForm,302,'Guardar texto libre compartido');
    ok(one('SELECT contenido FROM rutinas_semanales WHERE dia=?',[$routineDay])['contenido']===$routineSaved,'Saltos de línea preservados');
    send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['dia'=>7]),422,'Rechazar día inválido');
    send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['contenido'=>str_repeat('x',20001)]),422,'Validar límite de texto');
    ok(one('SELECT contenido FROM rutinas_semanales WHERE dia=?',[$routineDay])['contenido']===$routineSaved,'Errores no sobrescriben rutina');
    $secondStudent=client();
    send($secondStudent,'login.php',['usuario'=>$prefix.'_dos','password'=>$password,'csrf'=>token($secondStudent,'login.php')],302,'Login segundo alumno');
    $texts=[];
    foreach([$student,$secondStudent] as $pupil){
        [$status,$html]=http($pupil,'rutinas.php?dia='.$routineDay.'&alumno_id=999999');
        ok($status===200&&!str_contains($html,'<textarea')&&!str_contains($html,'<script>alert(1)</script>'),'Alumno solo lee texto seguro');
        $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $texts[]=$dom->getElementById('routine-content')->textContent;
    }
    ok($texts[0]===$routineSaved&&$texts[0]===$texts[1],'Ambos alumnos ven exactamente la misma rutina sin asignación');
    send($student,'rutinas.php?dia='.$routineDay,$routineForm,403,'Alumno no modifica rutina compartida');
    send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['csrf'=>'invalido']),403,'Rutina protegida por CSRF');
    $routineSaved=$prefix."\nRutina actualizada";
    send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['contenido'=>$routineSaved]),302,'Editar la rutina del mismo día');
    ok((int)one('SELECT COUNT(*) total FROM rutinas_semanales WHERE dia BETWEEN 1 AND 6')['total']===6,'Seis rutinas únicas, sin duplicados');
    ok(one('SELECT * FROM rutinas_semanales WHERE dia=?',[$otherDay])===$otherBefore,'Otros días no cambian');
    [$status,$html]=http($admin,'rutinas.php?dia='.$routineDay);
    ok($status===200&&str_contains($html,'<textarea')&&str_contains($html,escape($routineSaved))&&!str_contains($html,'Asignar rutina'),'Administrador tiene bloc de notas');
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    ok($dom->getElementById('routine-editor')->textContent==='','Bloc vacío después de guardar');
    ok($dom->getElementById('routine-content')->textContent===$routineSaved,'Rutina guardada visible en su cuadro');
    ok(strpos($html,'id="saved-routine"')>strpos($html,'id="routine-form"'),'Cuadro guardado debajo del bloc');
    $invalidDraft=str_repeat('x',20001);
    $errorHtml=send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['contenido'=>$invalidDraft]),422,'Error conserva borrador');
    $errorDom=new DOMDocument();$errorDom->loadHTML('<?xml encoding="UTF-8">'.$errorHtml);
    ok($errorDom->getElementById('routine-editor')->textContent===$invalidDraft,'Texto inválido permanece en el bloc');
    ok($errorDom->getElementById('routine-content')->textContent===$routineSaved,'Error no altera el cuadro guardado');

    ok(http($student,'rutinas.php?dia=0')[0]===400,'Consulta de día inválido rechazada');
    $routineSaved='';
    send($admin,'rutinas.php?dia='.$routineDay,array_replace($routineForm,['contenido'=>'']),302,'Vaciar un día');
    [$status,$html]=http($student,'rutinas.php?dia='.$routineDay);
    ok($status===200&&str_contains($html,'Todavía no se cargó'),'Día sin rutina muestra mensaje claro');

    ok(array_column(listado_horarios(),'hora')===horarios_gimnasio(),'Diez horarios exactos y ordenados');
    [$status,$html]=http($admin,'clases.php');
    ok($status===200&&str_contains($html,$paidName)&&str_contains($html,$lateName),'Listado de alumnos por horario');
    foreach(horarios_gimnasio() as $hora)ok(str_contains($html,'id="horario-'.str_replace(':','',$hora).'"'),'Horario '.$hora);
    [$status,$html]=http($student,'clases.php?alumno_id='.$students[1]);
    ok($status===200&&str_contains($html,'07:00')&&!str_contains($html,$lateName),'Alumno consulta solo su horario');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],array_replace($studentEdit,['horario_habitual'=>'12:00']),422,'Rechazar horario fuera de lista');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],array_replace($studentEdit,['horario_habitual'=>'']),422,'Horario obligatorio');
    send($admin,'alumnos.php?accion=editar&id='.$students[0],array_replace($studentEdit,['horario_habitual'=>'08:00']),302,'Cambiar horario');
    ok(one('SELECT horario_habitual FROM alumnos WHERE id=?',[$students[0]])['horario_habitual']==='08:00','Horario habitual guardado');

    [$status,$html]=http($admin,'alumnos.php?buscar='.$prefix.'_uno');
    ok($status===200&&str_contains($html,'Al día'),'Cuota en Alumnos');
    [$status,$html]=http($student,'alumno.php');
    ok($status===200&&str_contains($html,'Al día'),'Resumen del alumno');
    [$status,$html]=http($admin,'admin.php');
    ok($status===200&&!str_contains($html,'Módulo pendiente'),'Dashboard conectado');
    foreach(['schema.php','domain.php','module.php','actualizar.php','tests/integracion.php'] as $path)ok(http($admin,$path)[0]===403,'Archivo interno bloqueado '.$path);
    // Completar un horario hasta 19 lugares con cuentas de prueba.
    $targetHour='20:00';
    $occupied=(int)one("SELECT COUNT(*) total FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=? AND u.activo=1",[$targetHour])['total'];
    if($occupied>19)throw new RuntimeException('La prueba necesita un lugar libre a las 20:00.');
    for($i=$occupied;$i<19;$i++){
        $username=$prefix.'_cupo_'.$i;
        run_action('usuarios',array_merge(test_personal_data($username),['nombre'=>$username,'usuario'=>$username,'password'=>$password,'rol'=>'alumno','activo'=>'1','horario_habitual'=>$targetHour]),['id'=>$adminId,'rol'=>'administrador']);
        $userIds[]=(int)one('SELECT id FROM usuarios WHERE usuario=?',[$username])['id'];
    }
    $admin2=client();
    send($admin2,'login.php',['usuario'=>$prefix.'_admin','password'=>$password,'csrf'=>token($admin2,'login.php')],302,'Segunda sesión administrativa');
    $csrf2=token($admin2,'usuarios.php');
    $multi=curl_multi_init();
    foreach([[$admin,$csrf,$students[0],$paidName,$prefix.'_uno'],[$admin2,$csrf2,$students[1],$lateName,$prefix.'_dos']] as [$handle,$nonce,$sid,$name,$username]){
        $data=array_replace($studentEdit,test_personal_data($username),['nombre'=>$name,'usuario'=>$username,'csrf'=>$nonce,'horario_habitual'=>$targetHour]);
        curl_setopt_array($handle,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/alumnos.php?accion=editar&id='.$sid,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
        curl_multi_add_handle($multi,$handle);
    }
    do {curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1.0);}while($running);
    $codes=[curl_getinfo($admin,CURLINFO_HTTP_CODE),curl_getinfo($admin2,CURLINFO_HTTP_CODE)];sort($codes);
    ok($codes===[302,422],'Dos cambios simultáneos: solo uno obtiene el lugar 20');
    foreach([$admin,$admin2] as $h)curl_multi_remove_handle($multi,$h);curl_multi_close($multi);
    $winner=one('SELECT a.id,a.usuario_id,u.nombre,u.usuario FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.id IN (?,?) AND a.horario_habitual=?',[$students[0],$students[1],$targetHour]);
    $loserId=(int)$winner['id']===$students[0]?$students[1]:$students[0];
    $loser=one('SELECT a.*,u.nombre,u.usuario FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.id=?',[$loserId]);
    $count=(int)one("SELECT COUNT(*) total FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=? AND u.activo=1",[$targetHour])['total'];
    ok($count===20,'Cupo exacto de 20');
    send($admin,'usuarios.php',array_replace($base,['nombre'=>$prefix.'_extra','usuario'=>$prefix.'_extra','horario_habitual'=>$targetHour]),422,'Alta desde Usuarios respeta cupo');
    send($admin,'alumnos.php?accion=nuevo',array_replace($studentEdit,['nombre'=>$prefix.'_extra','usuario'=>$prefix.'_extra','password'=>$password,'horario_habitual'=>$targetHour]),422,'Alta desde Alumnos respeta cupo');
    send($admin,'alumnos.php',['operacion'=>'estado','id'=>$winner['id'],'activo'=>'0','csrf'=>$csrf],302,'Baja libera cupo');
    $loserEdit=array_replace($studentEdit,['nombre'=>$loser['nombre'],'usuario'=>$loser['usuario'],'horario_habitual'=>$targetHour]);
    send($admin,'alumnos.php?accion=editar&id='.$loserId,$loserEdit,302,'Lugar liberado disponible');
    send($admin,'alumnos.php',['operacion'=>'estado','id'=>$winner['id'],'activo'=>'1','csrf'=>$csrf],422,'Reactivación desde Alumnos respeta cupo');
    $reactivate=['id'=>$winner['usuario_id'],'nombre'=>$winner['nombre'],'usuario'=>$winner['usuario'],'password'=>'','activo'=>'1','horario_habitual'=>$targetHour,'csrf'=>$csrf];
    send($admin,'usuarios.php',$reactivate,422,'Reactivación desde Usuarios respeta cupo');
    send($admin,'usuarios.php',array_replace($reactivate,['horario_habitual'=>'13:00']),302,'Reactivar en otro horario');
    ok((int)one('SELECT activo FROM usuarios WHERE id=?',[$winner['usuario_id']])['activo']===1,'Cuenta reactivada');
    echo "Todas las pruebas pasaron.\n";
} finally {
    if($routineBackup!==null && $routineDay!==null && $routineSaved!==null){
        execute_sql('UPDATE rutinas_semanales SET contenido=?,actualizada_en=? WHERE dia=? AND contenido=?',[$routineBackup['contenido'],$routineBackup['actualizada_en'],$routineDay,$routineSaved]);
    }
    foreach($students as $id){

    }
    foreach($userIds as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}
    echo "Registros temporales eliminados.\n";
}




