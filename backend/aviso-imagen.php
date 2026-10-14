<?php
require __DIR__.'/bootstrap.php';
if(!current_user()){http_response_code(403);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(404);exit;}
$q=db()->prepare('SELECT imagen,imagen_tipo FROM avisos WHERE id=?');$q->execute([$id]);$notice=$q->fetch();
if(!$notice || $notice['imagen']===null || !in_array($notice['imagen_tipo'],['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
header('Content-Type: '.$notice['imagen_tipo']);header('Content-Length: '.strlen($notice['imagen']));
echo $notice['imagen'];