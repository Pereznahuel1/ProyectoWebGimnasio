<?php
declare(strict_types=1);
if (!function_exists('db')) { http_response_code(404); exit; }
function migrate_modules(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS avisos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contenido MEDIUMTEXT NOT NULL,
        imagen MEDIUMBLOB NULL,
        imagen_tipo VARCHAR(30) NULL,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL
    ) ENGINE=InnoDB");
    $noticeColumns=db()->query('SHOW COLUMNS FROM avisos')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('formato_html',$noticeColumns,true))db()->exec('ALTER TABLE avisos ADD COLUMN formato_html TINYINT NOT NULL DEFAULT 0');
    if(!in_array('imagen_archivo',$noticeColumns,true))db()->exec('ALTER TABLE avisos ADD COLUMN imagen_archivo VARCHAR(80) NULL');
    $columns=db()->query('SHOW COLUMNS FROM alumnos')->fetchAll(PDO::FETCH_COLUMN);
    foreach(['nombres'=>'VARCHAR(120)', 'apellido'=>'VARCHAR(120)', 'fecha_nacimiento'=>'DATE', 'dni'=>'VARCHAR(20)', 'correo'=>'VARCHAR(190)'] as $column=>$type){
        if(!in_array($column,$columns,true)) db()->exec("ALTER TABLE alumnos ADD COLUMN $column $type NULL");
    }
    if(!db()->query("SHOW INDEX FROM alumnos WHERE Key_name='uq_alumnos_dni'")->fetch()) db()->exec('ALTER TABLE alumnos ADD UNIQUE KEY uq_alumnos_dni (dni)');
    if(!in_array('ultimo_mes_abonado',$columns,true)){
        db()->exec('ALTER TABLE alumnos ADD COLUMN ultimo_mes_abonado DATE NULL');
    }
    $hadSchedules=(bool)db()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='turnos_gimnasio'")->fetchColumn();
    db()->exec("CREATE TABLE IF NOT EXISTS turnos_gimnasio (hora CHAR(5) PRIMARY KEY) ENGINE=InnoDB");
    $scheduleColumns=db()->query('SHOW COLUMNS FROM turnos_gimnasio')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('cupo',$scheduleColumns,true)) db()->exec('ALTER TABLE turnos_gimnasio ADD COLUMN cupo INT UNSIGNED NOT NULL DEFAULT 20');
    if(!$hadSchedules){
    $insert=db()->prepare('INSERT IGNORE INTO turnos_gimnasio(hora) VALUES(?)');
    foreach(['07:00','08:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'] as $hora) $insert->execute([$hora]);
    }
    if(!in_array('horario_habitual',$columns,true)){
        db()->exec('ALTER TABLE alumnos ADD COLUMN horario_habitual CHAR(5) NULL, ADD CONSTRAINT fk_alumno_turno FOREIGN KEY(horario_habitual) REFERENCES turnos_gimnasio(hora)');
    }
    db()->exec("CREATE TABLE IF NOT EXISTS reservas_diarias (
        alumno_id INT UNSIGNED NOT NULL,
        fecha DATE NOT NULL,
        hora CHAR(5) NULL,
        PRIMARY KEY(alumno_id,fecha),
        INDEX idx_reserva_fecha_hora(fecha,hora),
        FOREIGN KEY(alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
        FOREIGN KEY(hora) REFERENCES turnos_gimnasio(hora)
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS rutinas_semanales (
        dia TINYINT UNSIGNED PRIMARY KEY,
        contenido MEDIUMTEXT NOT NULL,
        actualizada_en DATETIME NULL
    ) ENGINE=InnoDB");
    $insert=db()->prepare("INSERT IGNORE INTO rutinas_semanales(dia,contenido) VALUES(?,'')");
    foreach(range(1,6) as $dia)$insert->execute([$dia]);
}
