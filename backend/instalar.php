<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/domain.php';

try {
    $config = require __DIR__ . '/config.php';
    $serverDsn = preg_replace('/;dbname=[^;]+/', '', $config['dsn']);
    $server = new PDO($serverDsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $server->exec('CREATE DATABASE IF NOT EXISTS sistema_gym CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    db()->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        rol ENUM('administrador','alumno') NOT NULL DEFAULT 'alumno',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS alumnos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NOT NULL UNIQUE,
        telefono VARCHAR(40) NULL,
        fecha_alta DATE NOT NULL,
        CONSTRAINT fk_alumnos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB");
    // Compatibilidad con instalaciones anteriores: conservar correos y contraseñas.
    $columns = db()->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('usuario', $columns, true)) {
        db()->exec("ALTER TABLE usuarios ADD COLUMN usuario VARCHAR(50) NULL UNIQUE AFTER nombre");
    }
    if (in_array('email', $columns, true)) {
        db()->exec("ALTER TABLE usuarios MODIFY email VARCHAR(190) NULL");
        db()->exec("UPDATE usuarios SET usuario = CONCAT('usuario_', id) WHERE usuario IS NULL");
        echo "Las cuentas anteriores sin usuario ahora usan usuario_ID (por ejemplo usuario_1). Conservan sus contraseñas.\n";
    }
    db()->exec("ALTER TABLE usuarios MODIFY usuario VARCHAR(50) NOT NULL");
    require_once __DIR__ . '/schema.php';
    migrate_modules();
    if (in_array('--solo-esquema', $argv, true)) {
        echo "Esquema actualizado.\n";
        exit;
    }
    echo "Base de datos lista. Crear una cuenta (Ctrl+C para cancelar).\n";
    $name = trim(readline('Nombre: '));
    $role = trim(readline('Rol (administrador/alumno): '));
    $usuario=$role==='alumno'?'pendiente':trim(readline('Usuario: '));
    $password=$role==='alumno'?'pendiente':readline('Contraseña: ');
    if ($name === '' || strlen($name) > 120 || !valid_username($usuario)
        || strlen($usuario) > 50 || !in_array($role, ['administrador', 'alumno'], true)
        || $password === '') {
        fwrite(STDERR, "Datos inválidos. Revisá el nombre, usuario y rol. Ingresá una contraseña.\n");
        exit(1);
    }
    $hour=$role==='alumno'?validar_horario(trim(readline('Horario habitual ('.implode(', ',horarios_gimnasio()).'): '))):null;
    $personal=null;
    if($role==='alumno') $personal=student_details(['nombre'=>$name,'apellido'=>trim(readline('Apellido: ')),'fecha_nacimiento'=>trim(readline('Fecha de nacimiento (AAAA-MM-DD): ')),'dni'=>trim(readline('DNI sin puntos: ')),'telefono'=>trim(readline('Teléfono: ')),'correo'=>trim(readline('Correo electrónico: '))]);
    if($personal){$usuario=$personal[3];$password=$personal[3];}
    db()->beginTransaction();
    bloquear_horarios();
    if($role==='alumno') comprobar_cupo($hour);
    $query = db()->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)');
    $query->execute([$name, $usuario, hash_password($password), $role]);
    if ($role === 'alumno') {
        $query = db()->prepare('INSERT INTO alumnos (usuario_id, fecha_alta, horario_habitual) VALUES (?, ?, ?)');
        $userId=(int)db()->lastInsertId();
        $query->execute([$userId,date('Y-m-d'),$hour]);
        save_student_details($userId,$personal);
    }
    db()->commit();
    echo "Cuenta creada. Ingresá en http://localhost:8080/sistema-gym/\n";
} catch (PDOException | InvalidArgumentException $exception) {
    if (isset($config)) {
        try { if (db()->inTransaction()) { db()->rollBack(); } } catch (PDOException) {}
    }
    if($exception instanceof InvalidArgumentException) { fwrite(STDERR,$exception->getMessage()."\n");exit(1); }
    fwrite(STDERR, "No se pudo completar la instalación. Revisá MySQL, config.php y que el usuario no exista.\n");
    exit(1);
}
