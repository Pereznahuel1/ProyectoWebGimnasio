<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__).'/domain.php';
function check($value,$message){if(!$value)throw new RuntimeException($message);echo "OK: $message\n";}
function rejected(callable $fn,$label){try{$fn();}catch(InvalidArgumentException $e){check(true,$label);return;}throw new RuntimeException($label);}
$prefix='qa_slots_'.bin2hex(random_bytes(5));
db()->beginTransaction();
try{
 bloquear_horarios();$free=[];
 for($i=0;$i<60 && count($free)<2;$i++){$hour=sprintf('00:%02d',$i);if(!one('SELECT hora FROM turnos_gimnasio WHERE hora=?',[$hour]))$free[]=$hour;}
 check(count($free)===2,'Horarios libres para prueba');[$first,$second]=$free;
 schedule_action(['operacion'=>'crear','hora'=>$first,'cupo'=>'2']);
 check(in_array($first,horarios_gimnasio(),true),'Alta disponible para asignar');
 rejected(fn()=>schedule_action(['operacion'=>'crear','hora'=>$first,'cupo'=>'2']),'Horario duplicado rechazado');
 rejected(fn()=>schedule_action(['operacion'=>'crear','hora'=>'25:00','cupo'=>'2']),'Hora inválida rechazada');
 rejected(fn()=>schedule_action(['operacion'=>'editar','original'=>$first,'hora'=>$first,'cupo'=>'0']),'Cupo cero rechazado');
 foreach([1,2] as $n){execute_sql("INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,'alumno')",[$prefix,$prefix.$n,hash_password('prueba')]);$uid=(int)db()->lastInsertId();comprobar_cupo($first);execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta,horario_habitual) VALUES(?,?,?)',[$uid,date('Y-m-d'),$first]);}
 rejected(fn()=>comprobar_cupo($first),'Cupo configurado se respeta');
 rejected(fn()=>schedule_action(['operacion'=>'editar','original'=>$first,'hora'=>$first,'cupo'=>'1']),'No reducir por debajo de ocupación');
 rejected(fn()=>schedule_action(['operacion'=>'eliminar','original'=>$first]),'No borrar horario ocupado');
 schedule_action(['operacion'=>'editar','original'=>$first,'hora'=>$second,'cupo'=>'3']);
 check((int)one('SELECT COUNT(*) n FROM alumnos WHERE horario_habitual=?',[$second])['n']===2 && !in_array($first,horarios_gimnasio(),true),'Cambio de hora conserva alumnos');
 comprobar_cupo($second);check(true,'Ampliación libera cupos');
 $slot=array_values(array_filter(listado_horarios(),fn($row)=>$row['hora']===$second))[0];check((int)$slot['cupo']===3 && (int)$slot['inscriptos']===2,'Resumen refleja cupo y ocupación');
 execute_sql('UPDATE usuarios SET activo=0 WHERE usuario=?',[$prefix.'2']);
 rejected(fn()=>schedule_action(['operacion'=>'eliminar','original'=>$second]),'Asignaciones inactivas también protegidas');
 execute_sql('UPDATE alumnos SET horario_habitual=NULL WHERE horario_habitual=?',[$second]);
 schedule_action(['operacion'=>'eliminar','original'=>$second]);check(!in_array($second,horarios_gimnasio(),true),'Eliminar horario vacío');
}finally{db()->rollBack();}