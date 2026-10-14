<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_classes_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];$studentIds=[];$hours=[];
try{
 db()->beginTransaction();bloquear_horarios();
 for($i=0;$i<60 && count($hours)<3;$i++){$hour=sprintf('23:%02d',$i);if(!one('SELECT hora FROM turnos_gimnasio WHERE hora=?',[$hour])){execute_sql('INSERT INTO turnos_gimnasio(hora,cupo) VALUES(?,1)',[$hour]);$hours[]=$hour;}}
 if(count($hours)!==3)throw new RuntimeException('No hay horarios de prueba');
 foreach([0,1] as $i){execute_sql("INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,'alumno')",[$prefix.$i,$prefix.$i,hash_password($password)]);$ids[]=(int)db()->lastInsertId();execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta,horario_habitual) VALUES(?,?,?)',[$ids[$i],date('Y-m-d'),$hours[$i]]);$studentIds[]=(int)db()->lastInsertId();}
 db()->commit();$date=fechas_clases(1)[1]->format('Y-m-d');$clients=[];$tokens=[];
 foreach([0,1] as $i){$clients[$i]=client();request($clients[$i],'login.php',['csrf'=>csrf($clients[$i],'login.php'),'usuario'=>$prefix.$i,'password'=>$password]);$tokens[$i]=csrf($clients[$i],'clases.php?semana=1');}
 $data=['csrf'=>$tokens[0],'operacion'=>'reservar','fecha'=>$date,'hora'=>$hours[2]];
 verify(request($clients[0],'clases.php?semana=1',array_replace($data,['csrf'=>'incorrecto']))[0]===403,'CSRF');
 verify(request($clients[0],'clases.php?semana=1',$data)[0]===302,'Reserva otro horario');
 verify(cupo_fecha($date,$hours[0])===0 && cupo_fecha($date,$hours[2])===1,'Mover libera horario anterior');
 verify(request($clients[1],'clases.php?semana=1',array_replace($data,['csrf'=>$tokens[1]]))[0]===422,'Cupo completo rechazado');
 verify(clase_alumno(one('SELECT * FROM alumnos WHERE id=?',[$studentIds[1]]),$date)===$hours[1],'Reserva anterior conservada ante error');
 verify(request($clients[0],'clases.php?semana=1',array_replace($data,['operacion'=>'cancelar','alumno_id'=>$studentIds[1]]))[0]===302,'Cancela solo su propia clase');
 verify(cupo_fecha($date,$hours[2])===0 && clase_alumno(one('SELECT * FROM alumnos WHERE id=?',[$studentIds[1]]),$date)===$hours[1],'Cancelación libera lugar sin tocar otro alumno');
 $multi=curl_multi_init();foreach([0,1] as $i){curl_setopt_array($clients[$i],[CURLOPT_URL=>'http://localhost:8080/sistema-gym/clases.php?semana=1',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(array_replace($data,['csrf'=>$tokens[$i]]))]);curl_multi_add_handle($multi,$clients[$i]);}
 do{curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1);}while($running);
 $statuses=[];foreach($clients as $c){$statuses[]=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_multi_remove_handle($multi,$c);}curl_multi_close($multi);sort($statuses);
 verify($statuses===[302,422] && cupo_fecha($date,$hours[2])===1,'Dos reservas simultáneas: solo una obtiene el último cupo');
 $sunday=(new DateTimeImmutable($date))->modify('+6 days')->format('Y-m-d');
 verify(request($clients[0],'clases.php',array_replace($data,['fecha'=>$sunday]))[0]===422,'Domingo rechazado');
 verify(request($clients[0],'clases.php',array_replace($data,['fecha'=>'2020-01-01']))[0]===422,'Fecha pasada rechazada');
 verify(one('SELECT horario_habitual FROM alumnos WHERE id=?',[$studentIds[0]])['horario_habitual']===$hours[0],'Horario habitual conservado');
 [$code,$html]=request($clients[0],'clases.php?semana=1');verify($code===200 && str_contains($html,'Mis clases') && !str_contains($html,$prefix.'1'),'Vista semanal sin datos de otros alumnos');
}finally{
 if(db()->inTransaction())db()->rollBack();
 foreach($ids as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}
 foreach($hours as $hour)execute_sql('DELETE FROM turnos_gimnasio WHERE hora=?',[$hour]);
}
