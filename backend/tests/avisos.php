<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/domain.php';
function verify($v,$m){if(!$v)throw new RuntimeException($m);echo "OK: $m\n";}
function client(){ $c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>15]);return $c; }
function request($c,$page,$data=null){curl_setopt_array($c,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/'.$page,CURLOPT_POST=>$data!==null]);if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($data));$s=curl_exec($c);if($s===false)throw new RuntimeException(curl_error($c));return [curl_getinfo($c,CURLINFO_HTTP_CODE),$s];}
function csrf($c,$page){[$code,$html]=request($c,$page);if($code!==200||!preg_match('/name="csrf" value="([^"]+)"/',$html,$m))throw new RuntimeException('Sin formulario');return $m[1];}
$prefix='qa_notice_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$ids=[];$noticeId=null;$file=tempnam(sys_get_temp_dir(),'gym');
try{
 foreach(['administrador','alumno'] as $role){execute_sql('INSERT INTO usuarios(nombre,usuario,password_hash,rol) VALUES(?,?,?,?)',[$prefix,$prefix.'_'.$role,hash_password($password),$role]);$ids[$role]=(int)db()->lastInsertId();}
 execute_sql('INSERT INTO alumnos(usuario_id,fecha_alta,nombres) VALUES(?,?,?)',[$ids['alumno'],date('Y-m-d'),'Prueba']);
 $admin=client();request($admin,'login.php',['csrf'=>csrf($admin,'login.php'),'usuario'=>$prefix.'_administrador','password'=>$password]);$token=csrf($admin,'avisos.php');
 $student=client();request($student,'login.php',['csrf'=>csrf($student,'login.php'),'usuario'=>$prefix.'_alumno','password'=>$password]);
 verify(request($student,'avisos.php')[0]===403,'Gestión exclusiva del administrador');
 verify(request($admin,'avisos.php',['csrf'=>'incorrecto','operacion'=>'guardar','contenido'=>$prefix])[0]===403,'CSRF obligatorio');
 verify(request($admin,'avisos.php',['csrf'=>$token,'operacion'=>'guardar','contenido'=>''])[0]===422,'Aviso vacío rechazado');
 file_put_contents($file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
 file_put_contents($file,str_repeat(' ',3*1024*1024),FILE_APPEND);
 curl_setopt_array($admin,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/avisos.php',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['csrf'=>$token,'operacion'=>'guardar','contenido'=>$prefix."
<script>alert(1)</script>",'imagen'=>new CURLFile($file,'image/png','prueba.png')]]);
 curl_exec($admin);verify(curl_getinfo($admin,CURLINFO_HTTP_CODE)===302,'Publicar texto e imagen');
 $notice=one('SELECT id FROM avisos WHERE contenido LIKE ?',[$prefix.'%']);$noticeId=(int)$notice['id'];
 [$status,$html]=request($student,'alumno.php');verify($status===200 && str_contains($html,'¡Hola, Prueba!') && str_contains($html,$prefix) && str_contains($html,'&lt;script&gt;'),'Dashboard saluda y muestra aviso escapado');
 verify(str_contains($html,'Mi cuota')&&str_contains($html,'Rutina de hoy')&&str_contains($html,'Mis clases'),'Tres tarjetas del dashboard');
 [$status,$bytes]=request($student,'aviso-imagen.php?id='.$noticeId);verify($status===200 && $bytes===file_get_contents($file),'Imagen visible para alumno');
 $anonymous=client();verify(request($anonymous,'aviso-imagen.php?id='.$noticeId)[0]===403,'Imagen requiere sesión');
 [$status,$html]=request($admin,'avisos.php');verify($status===200 && preg_match('/id="notice-editor"[^>]*><\/div>/',$html)===1,'Bloc vacío tras publicar');
 verify(request($admin,'avisos.php?editar='.$noticeId,['csrf'=>$token,'operacion'=>'guardar','contenido'=>$prefix.' editado','quitar_imagen'=>'1'])[0]===302,'Editar aviso y quitar imagen');
 verify(request($student,'aviso-imagen.php?id='.$noticeId)[0]===404,'Imagen retirada');
 file_put_contents($file,'<?php echo "no";');
 curl_setopt_array($admin,[CURLOPT_URL=>'http://localhost:8080/sistema-gym/avisos.php',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['csrf'=>$token,'operacion'=>'guardar','contenido'=>$prefix,'imagen'=>new CURLFile($file,'image/png','falso.png')]]);curl_exec($admin);verify(curl_getinfo($admin,CURLINFO_HTTP_CODE)===422,'Archivo falso rechazado');
 verify(request($student,'perfil.php')[0]===200,'Perfil sigue disponible');
 verify(request($admin,'avisos.php',['csrf'=>$token,'operacion'=>'eliminar','id'=>$noticeId])[0]===302 && one('SELECT id FROM avisos WHERE id=?',[$noticeId])===null,'Eliminar aviso');
}finally{
 if($noticeId)execute_sql('DELETE FROM avisos WHERE id=?',[$noticeId]);
 foreach($ids as $id){execute_sql('DELETE FROM alumnos WHERE usuario_id=?',[$id]);execute_sql('DELETE FROM usuarios WHERE id=?',[$id]);}
 if(is_file($file))unlink($file);
}
