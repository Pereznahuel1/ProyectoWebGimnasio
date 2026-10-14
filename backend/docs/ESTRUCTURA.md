# Estructura de SISTEMA FIT

## Pantallas

- index.php: redirección según rol.
- login.php y logout.php: acceso y cierre de sesión.
- admin.php y panel.php: dashboard del administrador.
- alumno.php, perfil.php y contrasena.php: dashboard, perfil y contraseña del alumno.
- alumnos.php y usuarios.php: gestión de alumnos y cuentas.
- cuotas.php: filtros y registro manual de cuotas.
- rutinas.php: rutina compartida de lunes a sábado.
- horarios.php: configuración de horarios y cupos.
- clases.php: clases por fecha, reservas y cancelaciones.
- avisos.php y aviso-imagen.php: publicación y consulta de avisos e imágenes.

## Archivos compartidos

- bootstrap.php: conexión, sesión, autenticación, CSRF y contraseñas.
- config.php: conexión a MySQL.
- domain.php: validaciones y operaciones del sistema.
- module.php: formularios y procesamiento compartido.
- layout.php: cabecera y menú.
- schema.php: migraciones.
- instalar.php y actualizar.php: herramientas CLI.

## Recursos

- assets/img/fit-control.svg: logo de mancuerna.
- css/styles.css: SB Admin; css/gym.css: ajustes propios.
- js/scripts.js: menú; js/routine-dates.js: fechas de rutinas.
- tests/: pruebas de integración.
- docs/HISTORIAL.md: documentación anterior, con algunas funciones reemplazadas.

Los archivos internos, la documentación y las pruebas tienen restricciones de acceso web mediante .htaccess.
