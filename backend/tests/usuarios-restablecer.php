<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_reset_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];
try{
 foreach(['administrador','alumno'] as $role){execute_sql('INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,?)',[$prefix.' '.$role,$prefix.'_'.$role,hash_password($password),$role]);$ids[$role]=(int)db()->lastInsertId();}
 do{$dni=(string)random_int(10000000,99999999);}while(one('SELECT id FROM alumnos WHERE dni=?',[$dni]));
 execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta,dni) VALUES(?,?,?)',[$ids['alumno'],date('Y-m-d'),$dni]);
 $admin=client();request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix.'_administrador','password'=>$password]);$token=csrf($admin,'usuarios.php');
 foreach([$prefix.' alumno',$prefix.'_alumno'] as $search){[$code,$html]=request($admin,'usuarios.php?buscar='.urlencode($search));verify($code===200 && str_contains($html,'<td>'.$prefix.'_alumno</td>')&&!str_contains($html,'<td>'.$prefix.'_administrador</td>'),'Filtro nombre o usuario');}
 $student=client();request($student,'login.php',['csrf'=>csrf($student,'login.php'),'usuario'=>$prefix.'_alumno','password'=>$password]);
 $data=['csrf'=>$token,'operacion'=>'restablecer','id'=>$ids['alumno']];
 verify(request($student,'usuarios.php',$data)[0]===403,'Alumno no restablece contraseñas');
 verify(request($admin,'usuarios.php',array_replace($data,['csrf'=>'incorrecto']))[0]===403,'CSRF requerido');
 verify(request($admin,'usuarios.php',array_replace($data,['id'=>$ids['administrador']]))[0]===422,'No restablece administradores');
 verify(request($admin,'usuarios.php?buscar='.$prefix,$data)[0]===302,'Restablecimiento exitoso');
 $account=one('SELECT usuario,activo,password_hash FROM usuarios WHERE id=?',[$ids['alumno']]);
 verify(password_verify($dni,$account['password_hash'])&&!password_verify($password,$account['password_hash']),'DNI reemplaza contraseña anterior');
 verify($account['usuario']===$prefix.'_alumno' && (int)$account['activo']===1,'Usuario y estado conservados');
 $fresh=client();verify(request($fresh,'login.php',['csrf'=>csrf($fresh,'login.php'),'usuario'=>$prefix.'_alumno','password'=>$dni])[0]===302,'Login con contraseña restablecida');
 execute_sql('UPDATE alumnos SET dni=NULL WHERE usuario_id=?',[$ids['alumno']]);
 verify(request($admin,'usuarios.php',$data)[0]===422,'Sin DNI se rechaza restablecimiento');
}finally{foreach($ids as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}}
