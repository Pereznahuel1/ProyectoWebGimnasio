<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/domain.php';
$user=require_role('administrador');
$today=date('Y-m-d');
$activeStudents=(int)one("SELECT COUNT(*) total FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.rol='alumno' AND u.activo=1")['total'];
$publishedRoutines=(int)one("SELECT COUNT(*) total FROM rutinas_semanales WHERE dia BETWEEN 1 AND 6 AND contenido<>''")['total'];
$scheduleSummary=listado_horarios();
$assignedStudents=array_sum(array_column($scheduleSummary,'inscriptos'));
$feeStats=one("SELECT COALESCE(SUM(a.ultimo_mes_abonado>=?),0) al_dia,COALESCE(SUM(a.ultimo_mes_abonado IS NULL OR a.ultimo_mes_abonado<?),0) vencidas FROM alumnos a JOIN usuarios u ON u.id=a.usuario_id WHERE u.rol='alumno'",[cuota_periodo_exigible(),cuota_periodo_exigible()]);
$dashboardWeekday=(int)date('N');
$dashboardRoutineDay=$dashboardWeekday===7?1:$dashboardWeekday;
$dashboardRoutine=one('SELECT contenido FROM rutinas_semanales WHERE dia=?',[$dashboardRoutineDay]);
$dashboardRoutineText=$dashboardRoutine['contenido']??'';
$dashboardRoutineDate=fechas_rutina()[$dashboardRoutineDay];
$title='Bienvenido!';require __DIR__.'/panel.php';

