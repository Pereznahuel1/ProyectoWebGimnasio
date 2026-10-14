<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_personal_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];
try{
 execute_sql("INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,'administrador')",[$prefix,$prefix,password_hash($password,PASSWORD_DEFAULT)]);$ids[]=(int)db()->lastInsertId();
 $admin=client();verify(request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix,'password'=>$password])[0]===302,'Login');$token=csrf($admin,'usuarios.php');
 do{$dni=(string)random_int(10000000,99999999);}while(one('SELECT id FROM alumnos WHERE dni=?',[$dni]));
 $slot=one("SELECT t.hora FROM turnos_gimnasio t WHERE (SELECT COUNT(*) FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE a.horario_habitual=t.hora AND u.activo=1)<20 LIMIT 1");if(!$slot)throw new RuntimeException('No hay cupo para prueba');
 $data=['operacion'=>'guardar','csrf'=>$token,'nombre'=>'Prueba','apellido'=>'Apellido compuesto','fecha_nacimiento'=>'1998-05-14','dni'=>$dni,'telefono'=>'+54 11 12345678','correo'=>'prueba@example.com','usuario'=>$prefix.'_student','password'=>$password,'horario_habitual'=>$slot['hora'],'ultimo_mes_abonado'=>''];
 verify(request($admin,'alumnos.php?accion=nuevo',array_replace($data,['correo'=>'']))[0]===422,'Correo obligatorio');
 verify(request($admin,'alumnos.php?accion=nuevo',array_replace($data,['fecha_nacimiento'=>'2030-01-01']))[0]===422,'Fecha futura rechazada');
 verify(request($admin,'alumnos.php?accion=nuevo',$data)[0]===302,'Alta completa desde Alumnos');
 $row=one('SELECT a.*,u.nombre FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.usuario=?',[$data['usuario']]);$ids[]=(int)$row['usuario_id'];
 verify($row['dni']===$dni && $row['apellido']===$data['apellido'] && $row['nombre']==='Prueba Apellido compuesto' && $row['correo']===$data['correo'],'Datos persistidos en MySQL');
 $account=array_replace($data,['operacion'=>'cuenta','rol'=>'alumno','activo'=>'1','usuario'=>$prefix.'_duplicate']);
 verify(request($admin,'usuarios.php',$account)[0]===422,'DNI duplicado rechazado en Usuarios');
 verify(one('SELECT id FROM usuarios WHERE usuario=?',[$account['usuario']])===null,'Alta duplicada revertida sin cuenta huérfana');
 verify(request($admin,'alumnos.php?accion=editar&id='.$row['id'],array_replace($data,['password'=>'','apellido'=>'Actualizado']))[0]===302,'Editar datos personales');
 verify(one('SELECT nombre FROM usuarios WHERE id=?',[$row['usuario_id']])['nombre']==='Prueba Actualizado','Nombre completo sincronizado');
 $account['dni']=null;do{$account['dni']=(string)random_int(10000000,99999999);}while(one('SELECT id FROM alumnos WHERE dni=?',[$account['dni']]));
 verify(request($admin,'usuarios.php',$account)[0]===302,'Alta completa desde Usuarios');$ids[]=(int)one('SELECT id FROM usuarios WHERE usuario=?',[$account['usuario']])['id'];
 [$status,$html]=request($admin,'alumnos.php?buscar='.$dni);verify($status===200 && str_contains($html,'>DNI</th>') && str_contains($html,$dni) && str_contains($html,'Prueba Actualizado'),'Listado y búsqueda por DNI');
}finally{
 foreach(array_reverse($ids) as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}
}
