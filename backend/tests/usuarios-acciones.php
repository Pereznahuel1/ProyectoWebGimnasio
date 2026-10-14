<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_actions_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];
try{
 foreach(['administrador','alumno'] as $role){execute_sql('INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,?)',[$prefix,$prefix.'_'.$role,password_hash($password,PASSWORD_DEFAULT),$role]);$ids[$role]=(int)db()->lastInsertId();}
 execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta) VALUES(?,?)',[$ids['alumno'],date('Y-m-d')]);$sid=(int)db()->lastInsertId();
 $admin=client();verify(request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix.'_administrador','password'=>$password])[0]===302,'Login admin temporal');$token=csrf($admin,'usuarios.php');
 $student=client();verify(request($student,'login.php',['csrf'=>csrf($student,'login.php'),'usuario'=>$prefix.'_alumno','password'=>$password])[0]===302,'Login alumno temporal');
 verify(request($student,'usuarios.php',['operacion'=>'eliminar','id'=>$ids['administrador']])[0]===403,'Alumno no puede eliminar cuentas');
 verify(request($admin,'usuarios.php',['operacion'=>'eliminar','id'=>$ids['alumno'],'csrf'=>'incorrecto'])[0]===403,'Eliminación protegida por CSRF');
 verify(request($admin,'usuarios.php',['operacion'=>'eliminar','id'=>$ids['administrador'],'csrf'=>$token])[0]===422,'No eliminar cuenta propia');
 verify(request($admin,'usuarios.php',['operacion'=>'deshabilitar','id'=>$ids['alumno'],'csrf'=>$token])[0]===302,'Deshabilitar desde Usuarios');
 verify((int)one('SELECT activo FROM usuarios WHERE id=?',[$ids['alumno']])['activo']===0 && one('SELECT id FROM alumnos WHERE id=?',[$sid])!==null,'Deshabilitación persistida; ficha conservada');
 $_SESSION=['user_id'=>$ids['alumno']];verify(current_user()===null,'Sesión deshabilitada pierde acceso');
 verify(request($admin,'usuarios.php',['operacion'=>'habilitar','id'=>$ids['alumno'],'csrf'=>$token])[0]===422,'Habilitar exige horario válido');
 verify(request($admin,'usuarios.php',['operacion'=>'eliminar','id'=>$ids['alumno'],'csrf'=>$token])[0]===302,'Eliminar desde Usuarios');
 verify(one('SELECT id FROM usuarios WHERE id=?',[$ids['alumno']])===null && one('SELECT id FROM alumnos WHERE id=?',[$sid])===null,'Cuenta y ficha eliminadas de MySQL');
 verify(request($admin,'usuarios.php',['operacion'=>'eliminar','id'=>$ids['alumno'],'csrf'=>$token])[0]===422,'Eliminar cuenta inexistente se rechaza');
 verify(request($admin,'usuarios.php')[0]===200,'Listado disponible tras operaciones');
}finally{
 foreach($ids as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}
}