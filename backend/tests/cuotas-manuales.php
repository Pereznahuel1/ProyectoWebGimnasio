<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_fee_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];
try{
 foreach(['administrador','alumno'] as $role){execute_sql('INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,?)',[$prefix,$prefix.'_'.$role,hash_password($password),$role]);$ids[$role]=(int)db()->lastInsertId();}
 execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta) VALUES(?,?)',[$ids['alumno'],date('Y-m-d')]);$sid=(int)db()->lastInsertId();
 do{$dni=(string)random_int(10000000,99999999);}while(one('SELECT id FROM alumnos WHERE dni=?',[$dni]));
 execute_sql('UPDATE alumnos SET dni=? WHERE id=?',[$dni,$sid]);
 $admin=client();request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix.'_administrador','password'=>$password]);$token=csrf($admin,'cuotas.php');
 $student=client();request($student,'login.php',['csrf'=>csrf($student,'login.php'),'usuario'=>$prefix.'_alumno','password'=>$password]);
 [$code,$html]=request($admin,'cuotas.php?buscar='.$dni.'&estado=vencida');
 verify($code===200 && str_contains($html,'<td>'.$dni.'</td>') && str_contains($html,'<th scope="col">DNI</th>'),'Filtro DNI y vencida; columna DNI');
 [$code,$html]=request($admin,'cuotas.php?buscar='.$prefix.'&estado=al_dia');
 verify($code===200 && !str_contains($html,'<td>'.$dni.'</td>'),'Filtro de estado excluye deuda');
 $data=['csrf'=>$token,'operacion'=>'marcar_al_dia','alumno_id'=>$sid];
 verify(request($student,'cuotas.php',$data)[0]===403,'Alumno no registra cuotas');
 verify(request($admin,'cuotas.php',array_replace($data,['csrf'=>'incorrecto']))[0]===403,'CSRF requerido');
 verify(request($admin,'cuotas.php',$data)[0]===302,'Registro manual desde Cuotas');
 $paid=one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$sid])['ultimo_mes_abonado'];verify($paid===date('Y-m-01') && cuota_al_dia($paid),'Mes persistido y cuota al día');
 [$code,$html]=request($admin,'cuotas.php?buscar='.$prefix.'&estado=al_dia');
 verify($code===200 && str_contains($html,'<td>'.$dni.'</td>'),'Filtro por nombre y al día');
 [$code,$html]=request($student,'cuotas.php?buscar=inexistente&estado=vencida');
 verify($code===200 && str_contains($html,'<td>'.$dni.'</td>'),'Alumno conserva vista de su propia cuota');
 verify(request($admin,'cuotas.php',$data)[0]===302,'Repetición segura');
 $future=(new DateTimeImmutable('first day of next month'))->format('Y-m-d');execute_sql('UPDATE alumnos SET ultimo_mes_abonado=? WHERE id=?',[$future,$sid]);request($admin,'cuotas.php',$data);
 verify(one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$sid])['ultimo_mes_abonado']===$future,'Conserva pagos adelantados');
 $overdue=array_replace($data,['operacion'=>'marcar_vencida']);
 verify(request($student,'cuotas.php',$overdue)[0]===403,'Alumno no puede marcar vencida');
 verify(request($admin,'cuotas.php',array_replace($overdue,['csrf'=>'incorrecto']))[0]===403,'Marcar vencida exige CSRF');
 verify(request($admin,'cuotas.php?buscar='.$dni.'&estado=al_dia',$overdue)[0]===302,'Corrección manual a vencida');
 $paid=one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$sid])['ultimo_mes_abonado'];
 verify($paid===null && !cuota_al_dia($paid),'Corrección persistida en MySQL');
 [$code,$html]=request($admin,'cuotas.php?buscar='.$dni.'&estado=vencida');
 verify($code===200 && str_contains($html,'<td>'.$dni.'</td>'),'Alumno aparece entre vencidas');
 verify(request($admin,'cuotas.php',$data)[0]===302 && cuota_al_dia(one('SELECT ultimo_mes_abonado FROM alumnos WHERE id=?',[$sid])['ultimo_mes_abonado']),'Se puede volver a marcar al día');
 verify(request($admin,'cuotas.php',array_replace($data,['alumno_id'=>2147483647]))[0]===422,'Alumno inexistente rechazado');
}finally{foreach($ids as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}}
