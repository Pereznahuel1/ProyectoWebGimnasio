<?php
declare(strict_types=1);
if (!function_exists('db')) { http_response_code(404); exit; }
date_default_timezone_set('America/Argentina/Buenos_Aires');
function rows(string $sql, array $params=[]): array { $q=db()->prepare($sql); $q->execute($params); return $q->fetchAll(); }
function one(string $sql, array $params=[]): ?array { return rows($sql,$params)[0] ?? null; }
function execute_sql(string $sql,array $params=[]): void { $q=db()->prepare($sql);$q->execute($params); }
function field(array $data,string $key,int $max=120,bool $required=true): string {
    $value=is_string($data[$key]??null)?trim($data[$key]):'';
    if (($required && $value==='') || mb_strlen($value)>$max) { throw new InvalidArgumentException('Revisá el campo '.$key.'.'); }
    return $value;
}
function number(array $data,string $key,int $max=2147483647): int {
    $n=filter_var($data[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$max]]);
    if ($n===false) throw new InvalidArgumentException('Valor inválido: '.$key.'.');
    return $n;
}
function day(array $data,string $key): string {
    $value=field($data,$key,10);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if (!$date || $date->format('Y-m-d')!==$value || $value<'2000-01-01' || $value>'2100-12-31') throw new InvalidArgumentException('Fecha inválida: '.$key.'.');
    return $value;
}
function period(array $data): array {
    $start=day($data,'inicio');$end=day($data,'fin');
    if ($end<$start) throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
    return [$start,$end];
}
function active_student(int $id): array {
    $student=one("SELECT a.* FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.id=? AND u.rol='alumno' AND u.activo=1 FOR UPDATE",[$id]);
    if (!$student) throw new InvalidArgumentException('Seleccioná un alumno activo.');
    return $student;
}
function student_details(array $data): array {
    $name=field($data,'nombre',60);$surname=field($data,'apellido',59);
    $birth=field($data,'fecha_nacimiento',10);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);
    if(!$date || $date->format('Y-m-d')!==$birth || $birth<'1900-01-01' || $birth>date('Y-m-d')) throw new InvalidArgumentException('Ingresá una fecha de nacimiento válida.');
    $dni=field($data,'dni',20);
    if(!preg_match('/\A[0-9]{7,8}\z/',$dni)) throw new InvalidArgumentException('El DNI debe tener 7 u 8 números, sin puntos.');
    $phone=field($data,'telefono',40);
    if(!preg_match('/\A[+0-9() .-]{6,40}\z/',$phone) || strlen(preg_replace('/[^0-9]/','',$phone))<6) throw new InvalidArgumentException('Ingresá un teléfono válido.');
    $email=field($data,'correo',190);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ingresá un correo electrónico válido.');
    return [$name,$surname,$birth,$dni,$phone,$email];
}
function save_student_details(int $userId,array $details): void {
    execute_sql('UPDATE alumnos SET nombres=?,apellido=?,fecha_nacimiento=?,dni=?,telefono=?,correo=? WHERE usuario_id=?',[...$details,$userId]);
    execute_sql('UPDATE usuarios SET nombre=? WHERE id=?',[$details[0].' '.$details[1],$userId]);
}
function account_action(array $data,array $actor): string {
    bloquear_horarios();
    $id=isset($data['id']) && $data['id']!==''?number($data,'id'):0;
    if(!$id && ($data['rol']??'')==='alumno'){
        $details=student_details($data);
        $data['usuario']=$details[3];$data['password']=$details[3];
    }
    $name=field($data,'nombre');$username=field($data,'usuario',50);
    if (!valid_username($username)) throw new InvalidArgumentException('Usuario inválido: de 3 a 50 letras, números, puntos o guiones.');
    $password=is_string($data['password']??null)?$data['password']:'';
    if(!$id && $password==='') throw new InvalidArgumentException('Ingresá una contraseña.');
    $state=field($data,'activo',1);
    if (!in_array($state,['0','1'],true)) throw new InvalidArgumentException('Estado inválido.');
    if ($id) {
        // Bloquear el conjunto de administradores evita desactivar simultáneamente al último.
        $admins=rows("SELECT id FROM usuarios WHERE rol='administrador' AND activo=1 ORDER BY id FOR UPDATE");
        $target=one('SELECT * FROM usuarios WHERE id=? FOR UPDATE',[$id]);
        if (!$target) throw new InvalidArgumentException('Cuenta no encontrada.');
        if ($state==='0' && (int)$actor['id']===$id) throw new InvalidArgumentException('No podés desactivar tu propia cuenta.');
        if ($state==='0' && $target['rol']==='administrador' && $target['activo'] && count($admins)<=1) throw new InvalidArgumentException('Debe quedar un administrador activo.');
        if($target['rol']==='alumno'){
            $student=one('SELECT id,horario_habitual FROM alumnos WHERE usuario_id=? FOR UPDATE',[$id]);
            if(!$student) throw new InvalidArgumentException('Ficha de alumno no encontrada.');
            $hour=validar_horario(field($data,'horario_habitual',5));
            if($state==='1') comprobar_cupo($hour,(int)$student['id']);
            execute_sql('UPDATE alumnos SET horario_habitual=? WHERE id=?',[$hour,$student['id']]);
        }
        execute_sql('UPDATE usuarios SET nombre=?,usuario=?,activo=? WHERE id=?',[$name,$username,$state,$id]);
        if ($password!=='') execute_sql('UPDATE usuarios SET password_hash=? WHERE id=?',[hash_password($password),$id]);
    } else {
        $role=field($data,'rol',20);
        if (!in_array($role,['administrador','alumno'],true)) throw new InvalidArgumentException('Solo se permiten Administrador y Alumno.');
        execute_sql('INSERT INTO usuarios(nombre,usuario,password_hash,rol,activo) VALUES(?,?,?,?,?)',[$name,$username,hash_password($password),$role,$state]);
        $id=(int)db()->lastInsertId();
        if ($role==='alumno') {
            $hour=validar_horario(field($data,'horario_habitual',5));
            if($state==='1') comprobar_cupo($hour);
            execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta,horario_habitual) VALUES(?,?,?)',[$id,date('Y-m-d'),$hour]);
        }
    }
    if(($target['rol']??$role??'')==='alumno') save_student_details($id,student_details($data));
    return 'Cuenta guardada.';
}
function account_status_action(array $data,array $actor): string {
    bloquear_horarios();
    $id=number($data,'id');
    $operation=field($data,'operacion',30);
    $admins=rows("SELECT id FROM usuarios WHERE rol='administrador' AND activo=1 ORDER BY id FOR UPDATE");
    $target=one('SELECT * FROM usuarios WHERE id=? FOR UPDATE',[$id]);
    if(!$target) throw new InvalidArgumentException('Cuenta no encontrada.');
    if((int)$actor['id']===$id) throw new InvalidArgumentException('No podés deshabilitar ni eliminar tu propia cuenta.');
    if($operation!=='habilitar' && $target['rol']==='administrador' && $target['activo'] && count($admins)<=1)
        throw new InvalidArgumentException('Debe quedar un administrador activo.');
    if($operation==='eliminar'){
        $student=one('SELECT id FROM alumnos WHERE usuario_id=? FOR UPDATE',[$id]);
        if($student){
            // Las instalaciones anteriores pueden conservar inscripciones de clases.
            if(one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inscripciones'"))
                execute_sql('DELETE FROM inscripciones WHERE alumno_id=?',[$student['id']]);
            execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);
        }
        execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);
        return 'Usuario y sus datos asociados eliminados definitivamente.';
    }
    if($operation==='habilitar' && $target['rol']==='alumno'){
        $student=one('SELECT id,horario_habitual FROM alumnos WHERE usuario_id=? FOR UPDATE',[$id]);
        if(!$student || !$student['horario_habitual']) throw new InvalidArgumentException('Editá el alumno y asignale un horario antes de habilitarlo.');
        comprobar_cupo($student['horario_habitual'],(int)$student['id']);
    }
    execute_sql('UPDATE usuarios SET activo=? WHERE id=?',[$operation==='habilitar'?1:0,$id]);
    return $operation==='habilitar'?'Usuario habilitado.':'Usuario deshabilitado.';
}
function reset_student_password(array $data): string {
    bloquear_horarios();
    $id=number($data,'id');
    $target=one('SELECT rol FROM usuarios WHERE id=? FOR UPDATE',[$id]);
    if(!$target || $target['rol']!=='alumno')throw new InvalidArgumentException('Solo se puede restablecer la contraseña de un alumno.');
    $student=one('SELECT dni FROM alumnos WHERE usuario_id=? FOR UPDATE',[$id]);
    if(!$student || !preg_match('/\A[0-9]{7,8}\z/',$student['dni']??''))throw new InvalidArgumentException('Completá un DNI válido en la ficha del alumno antes de restablecer su contraseña.');
    execute_sql('UPDATE usuarios SET password_hash=? WHERE id=?',[hash_password($student['dni']),$id]);
    return 'Contraseña restablecida al DNI del alumno. Su usuario y estado no cambiaron.';
}
function user_action(array $data,array $actor): string {
    return match($data['operacion']??'cuenta') {
        'cuenta'=>account_action($data,$actor),
        'restablecer'=>reset_student_password($data),
        'habilitar','deshabilitar','eliminar'=>account_status_action($data,$actor),
        default=>throw new InvalidArgumentException('Operación inválida.')
    };
}
function dias_semana(): array {
    return [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'];
}
function routine_action(array $data): string {
    if(field($data,'operacion',30)!=='rutina_semanal') throw new InvalidArgumentException('Operación inválida.');
    $day=number($data,'dia',6);
    if(!is_string($data['contenido']??null)) throw new InvalidArgumentException('Escribí el texto de la rutina.');
    $content=str_replace(["\r\n","\r"],"\n",$data['contenido']);
    if(mb_strlen($content,'UTF-8')>20000) throw new InvalidArgumentException('La rutina admite hasta 20.000 caracteres.');
    if(trim($content)==='')$content='';
    execute_sql('UPDATE rutinas_semanales SET contenido=?,actualizada_en=? WHERE dia=?',[$content,date('Y-m-d H:i:s'),$day]);
    return $content===''?'Rutina de '.dias_semana()[$day].' vaciada.':'Rutina de '.dias_semana()[$day].' guardada para todos los alumnos.';
}
function run_action(string $module,array $data,array $actor): string {
    if ($actor['rol']!=='administrador') throw new InvalidArgumentException('Acceso exclusivo del administrador.');
    db()->beginTransaction();
    try {
        $message=match($module) {
            'usuarios'=>user_action($data,$actor),
            'rutinas'=>routine_action($data),
            'horarios'=>schedule_action($data),
            default=>throw new InvalidArgumentException('Módulo inválido.')
        };
        db()->commit();return $message;
    } catch (Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
}

function cuota_periodo_exigible(?string $today=null): string {
    $date=new DateTimeImmutable($today??date('Y-m-d'));
    $month=$date->modify('first day of this month');
    if((int)$date->format('j')<=10) $month=$month->modify('-1 month');
    return $month->format('Y-m-d');
}
function cuota_al_dia(?string $ultimoMes,?string $today=null): bool {
    return $ultimoMes!==null && $ultimoMes!=='' && $ultimoMes>=cuota_periodo_exigible($today);
}
function validar_mes_abonado(string $value): ?string {
    if($value==='') return null;
    if(!preg_match('/\A[0-9]{4}-(?:0[1-9]|1[0-2])\z/',$value)) throw new InvalidArgumentException('El último mes abonado debe ser un mes válido.');
    return day(['mes'=>$value.'-01'],'mes');
}


function horarios_gimnasio(): array { return array_column(rows('SELECT hora FROM turnos_gimnasio ORDER BY hora'),'hora'); }
function bloquear_horarios(): void {
    if(!db()->inTransaction()) throw new LogicException('La asignación necesita una transacción.');
    rows('SELECT hora FROM turnos_gimnasio ORDER BY hora FOR UPDATE');
}
function validar_horario(string $hora): string {
    if(!in_array($hora,horarios_gimnasio(),true)) throw new InvalidArgumentException('Seleccioná un horario del gimnasio.');
    return $hora;
}
function comprobar_cupo(string $hora,int $alumnoId=0): void {
    validar_horario($hora);
    $count=one("SELECT COUNT(*) total FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=? AND u.activo=1 AND u.rol='alumno' AND a.id<>?",[$hora,$alumnoId]);
    $capacity=(int)one('SELECT cupo FROM turnos_gimnasio WHERE hora=?',[$hora])['cupo'];
    foreach(rows('SELECT DISTINCT fecha FROM reservas_diarias WHERE fecha>=?',[date('Y-m-d')]) as $dateRow){
        if($alumnoId && one('SELECT alumno_id FROM reservas_diarias WHERE alumno_id=? AND fecha=?',[$alumnoId,$dateRow['fecha']]))continue;
        if(cupo_fecha($dateRow['fecha'],$hora,$alumnoId)>=$capacity)throw new InvalidArgumentException('El horario '.$hora.' no tiene cupo el '.date('d/m/Y',strtotime($dateRow['fecha'])).'.');
    }
    if((int)$count['total']>=$capacity) throw new InvalidArgumentException('El horario '.$hora.' ya tiene su cupo de '.$capacity.' alumnos completo. Elegí otro horario.');
}
function listado_horarios(): array {
    return rows("SELECT t.hora,t.cupo,COUNT(a.id) inscriptos FROM turnos_gimnasio t LEFT JOIN (alumnos a INNER JOIN usuarios u ON u.id=a.usuario_id AND u.activo=1 AND u.rol='alumno') ON a.horario_habitual=t.hora GROUP BY t.hora,t.cupo ORDER BY t.hora");
}


function fechas_rutina(?DateTimeImmutable $now=null): array {
    $zone=new DateTimeZone('America/Argentina/Buenos_Aires');
    $today=($now??new DateTimeImmutable('now',$zone))->setTimezone($zone)->setTime(0,0);
    $weekday=(int)$today->format('N');
    $monday=$weekday===7?$today->modify('+1 day'):$today->modify('-'.($weekday-1).' days');
    $dates=[];
    foreach(range(1,6) as $day)$dates[$day]=$monday->modify('+'.($day-1).' days');
    return $dates;
}
function schedule_action(array $data): string {
    bloquear_horarios();
    $op=field($data,'operacion',20);
    if(!in_array($op,['crear','editar','eliminar'],true))throw new InvalidArgumentException('Operación inválida.');
    $original=$op==='crear'?null:validar_horario(field($data,'original',5));
    if($op==='eliminar'){
        if(one('SELECT id FROM alumnos WHERE horario_habitual=? LIMIT 1',[$original]))throw new InvalidArgumentException('Este horario tiene alumnos asignados. Cambiá su horario antes de eliminarlo, incluidos los alumnos inactivos.');
        if(one('SELECT alumno_id FROM reservas_diarias WHERE hora=? LIMIT 1',[$original]))throw new InvalidArgumentException('Este horario tiene reservas registradas y no se puede eliminar.');
        execute_sql('DELETE FROM turnos_gimnasio WHERE hora=?',[$original]);
        return 'Horario eliminado.';
    }
    $hora=field($data,'hora',5);
    if(!preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/',$hora))throw new InvalidArgumentException('Ingresá una hora válida.');
    $cupo=number($data,'cupo',10000);
    if($op==='crear' || $hora!==$original){
        if(one('SELECT hora FROM turnos_gimnasio WHERE hora=?',[$hora]))throw new InvalidArgumentException('Ese horario ya existe.');
    }
    if($original!==null){
        $occupied=one("SELECT COUNT(*) total FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=? AND u.activo=1 AND u.rol='alumno'",[$original]);
        foreach(rows('SELECT DISTINCT fecha FROM reservas_diarias WHERE fecha>=?',[date('Y-m-d')]) as $dateRow)if(cupo_fecha($dateRow['fecha'],$original)>$cupo)throw new InvalidArgumentException('El cupo es menor a las reservas del '.date('d/m/Y',strtotime($dateRow['fecha'])).'.');
        if($cupo<(int)$occupied['total'])throw new InvalidArgumentException('El cupo no puede ser menor a los '.(int)$occupied['total'].' alumnos activos asignados.');
    }
    if($op==='crear')execute_sql('INSERT INTO turnos_gimnasio(hora,cupo) VALUES(?,?)',[$hora,$cupo]);
    elseif($hora===$original)execute_sql('UPDATE turnos_gimnasio SET cupo=? WHERE hora=?',[$cupo,$original]);
    else{
        execute_sql('INSERT INTO turnos_gimnasio(hora,cupo) VALUES(?,?)',[$hora,$cupo]);
        execute_sql('UPDATE alumnos SET horario_habitual=? WHERE horario_habitual=?',[$hora,$original]);
        execute_sql('UPDATE reservas_diarias SET hora=? WHERE hora=?',[$hora,$original]);
        execute_sql('DELETE FROM turnos_gimnasio WHERE hora=?',[$original]);
    }
    return 'Horario guardado.';
}

function fechas_clases(int $week=0): array {
    $dates=fechas_rutina();
    if($week===1)foreach($dates as &$date)$date=$date->modify('+7 days');
    return $dates;
}
function alumnos_clase(string $date): array {
    if((int)(new DateTimeImmutable($date))->format('N')===7)return [];
    return rows("SELECT a.id,u.nombre,CASE WHEN r.alumno_id IS NULL THEN a.horario_habitual ELSE r.hora END hora FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id LEFT JOIN reservas_diarias r ON r.alumno_id=a.id AND r.fecha=? WHERE u.rol='alumno' AND u.activo=1 AND a.fecha_alta<=? ORDER BY u.nombre COLLATE utf8mb4_spanish_ci,a.id",[$date,$date]);
}
function cupo_fecha(string $date,string $hour,int $except=0): int {
    $count=0;foreach(alumnos_clase($date) as $student)if($student['hora']===$hour && (int)$student['id']!==$except)$count++;
    return $count;
}
function clase_alumno(array $student,string $date): ?string {
    if($date<$student['fecha_alta'])return null;
    $override=one('SELECT hora FROM reservas_diarias WHERE alumno_id=? AND fecha=?',[$student['id'],$date]);
    return $override!==null?$override['hora']:$student['horario_habitual'];
}
function clase_modificable(string $date,string $hour): bool {
    return new DateTimeImmutable($date.' '.$hour)>new DateTimeImmutable('now');
}
function reserva_action(array $data,array $actor): string {
    if($actor['rol']!=='alumno')throw new InvalidArgumentException('Solo el alumno puede gestionar sus clases.');
    db()->beginTransaction();
    try{
        bloquear_horarios();
        $student=one("SELECT a.* FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.usuario_id=? AND u.activo=1 AND u.rol='alumno' FOR UPDATE",[$actor['id']]);
        if(!$student)throw new InvalidArgumentException('Alumno no encontrado.');
        $date=day($data,'fecha');$allowed=[];
        foreach([0,1] as $week)foreach(fechas_clases($week) as $d)$allowed[]=$d->format('Y-m-d');
        if(!in_array($date,$allowed,true) || $date<date('Y-m-d'))throw new InvalidArgumentException('Elegí un día disponible de esta semana o la próxima.');
        $current=clase_alumno($student,$date);
        if($current!==null && !clase_modificable($date,$current))throw new InvalidArgumentException('La clase ya comenzó. No se puede cambiar ni cancelar.');
        $op=field($data,'operacion',20);
        if($op==='cancelar'){
            if($current===null)throw new InvalidArgumentException('No tenés una clase reservada para ese día.');
            $hour=null;
        }elseif($op==='reservar'){
            $hour=validar_horario(field($data,'hora',5));
            if(!clase_modificable($date,$hour))throw new InvalidArgumentException('Ese horario ya comenzó.');
            $capacity=(int)one('SELECT cupo FROM turnos_gimnasio WHERE hora=?',[$hour])['cupo'];
            if(cupo_fecha($date,$hour,(int)$student['id'])>=$capacity)throw new InvalidArgumentException('Ese horario ya no tiene cupo. Elegí otro.');
        }else throw new InvalidArgumentException('Operación inválida.');
        execute_sql('INSERT INTO reservas_diarias(alumno_id,fecha,hora) VALUES(?,?,?) ON DUPLICATE KEY UPDATE hora=VALUES(hora)',[$student['id'],$date,$hour]);
        db()->commit();return $op==='cancelar'?'Clase cancelada. Tu lugar quedó disponible.':'Clase reservada para el '.date('d/m',strtotime($date)).' a las '.$hour.'.';
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function clean_notice_html(string $html): string {
    $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    libxml_clear_errors();libxml_use_internal_errors($previous);
    $render=function($node)use(&$render): string {
        if($node instanceof DOMText)return escape($node->textContent);
        if(!($node instanceof DOMElement))return '';
        $tag=strtolower($node->tagName);
        if(in_array($tag,['script','style','iframe','object','svg','math','template'],true))return '';
        $text='';foreach($node->childNodes as $child)$text.=$render($child);
        if($tag==='br')return '<br>';
        if(!in_array($tag,['p','div','strong','b','em','i','h2','h3'],true))return $text;
        return '<'.$tag.'>'.$text.'</'.$tag.'>';
    };
    $out='';foreach($doc->getElementsByTagName('body')->item(0)->childNodes as $node)$out.=$render($node);
    return trim(strip_tags(str_replace('&nbsp;',' ',$out)))===''?'':$out;
}
function notice_html(array $notice): string {
    return !empty($notice['formato_html'])?clean_notice_html($notice['contenido']):nl2br(escape($notice['contenido']));
}
