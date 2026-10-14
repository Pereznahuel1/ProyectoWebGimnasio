<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_dni_'.bin2hex(random_bytes(5));$ids=[];$secret=bin2hex(random_bytes(12));
try{
execute_sql("INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,'administrador')",[$prefix,$prefix,hash_password($secret)]);$ids[]=(int)db()->lastInsertId();
$admin=client();verify(request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix,'password'=>$secret])[0]===302,'Login administrador');
$hour=one("SELECT t.hora FROM turnos_gimnasio t WHERE (SELECT COUNT(*) FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=t.hora AND u.activo=1)<19 LIMIT 1")['hora'];
foreach(['usuarios.php','alumnos.php?accion=nuevo'] as $page){
 do{$dni=(string)random_int(10000000,99999999);}while(one('SELECT id FROM usuarios WHERE usuario=?',[$dni])||one('SELECT id FROM alumnos WHERE dni=?',[$dni]));
 $data=['csrf'=>csrf($admin,'usuarios.php'),'operacion'=>$page==='usuarios.php'?'cuenta':'guardar','nombre'=>'Prueba','apellido'=>'DNI','dni'=>$dni,'fecha_nacimiento'=>'1995-01-01','telefono'=>'1123456789','correo'=>'test@example.com','rol'=>'alumno','activo'=>'1','horario_habitual'=>$hour];
 verify(request($admin,$page,$data)[0]===302,'Alta con DNI sin campos de acceso: '.$page);
 $row=one('SELECT * FROM usuarios WHERE usuario=?',[$dni]);$ids[]=(int)$row['id'];verify(password_verify($dni,$row['password_hash']),'DNI como contraseña inicial');
}
$student=client();verify(request($student,'login.php',['csrf'=>csrf($student,'login.php'),'usuario'=>$dni,'password'=>$dni])[0]===302,'Alumno ingresa con DNI');
$token=csrf($student,'contrasena.php');
verify(request($student,'contrasena.php',['csrf'=>'incorrecto','actual'=>$dni,'nueva'=>'x','repetir'=>'x'])[0]===403,'Cambio protegido por CSRF');
verify(request($student,'contrasena.php',['csrf'=>$token,'actual'=>'incorrecta','nueva'=>'x','repetir'=>'x'])[0]===422,'Exige contraseña actual');
verify(request($student,'contrasena.php',['csrf'=>$token,'actual'=>$dni,'nueva'=>'x','repetir'=>'y'])[0]===422,'Exige confirmación coincidente');
verify(request($student,'contrasena.php',['csrf'=>$token,'actual'=>$dni,'nueva'=>'x','repetir'=>'x'])[0]===302,'Acepta contraseña de un carácter');
$long=str_repeat('a',200).'final';
verify(request($student,'contrasena.php',['csrf'=>csrf($student,'contrasena.php'),'actual'=>'x','nueva'=>$long,'repetir'=>$long])[0]===302,'Acepta contraseña de más de 72 caracteres');
$hash=one('SELECT password_hash FROM usuarios WHERE id=?',[$row['id']])['password_hash'];verify(password_verify($long,$hash)&&!password_verify(str_repeat('a',200).'otra',$hash)&&!password_verify($dni,$hash),'Contraseña completa sin truncamiento; DNI anterior invalidado');
$fresh=client();verify(request($fresh,'login.php',['csrf'=>csrf($fresh,'login.php'),'usuario'=>$dni,'password'=>$long])[0]===302,'Ingreso con nueva contraseña larga');
}finally{foreach(array_reverse($ids) as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}}
