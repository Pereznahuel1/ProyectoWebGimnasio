# SISTEMA FIT

Gestión de gimnasios con PHP, MySQL/MariaDB y SB Admin.

## Iniciar

Iniciar Apache y MySQL en XAMPP y abrir http://localhost:8080/sistema-gym/.
La conexión se configura en config.php.

Instalación nueva: ejecutar php instalar.php con el PHP de XAMPP.
Actualizar una instalación existente: ejecutar php actualizar.php.

## Organización

- PHP en la raíz: pantallas y lógica compartida, con sus rutas actuales.
- assets/img/: logo de la aplicación.
- css/: plantilla SB Admin y ajustes propios.
- js/: menú lateral y fechas de rutinas.
- docs/: estructura y notas históricas.
- tests/: pruebas de integración, bloqueadas para acceso web.
- login.html: redirección de compatibilidad a login.php.

## Funciones

Administrador: alumnos, usuarios, cuotas manuales, rutinas, horarios y cupos, clases por fecha y avisos con texto enriquecido e imágenes.

Alumno: dashboard, perfil, cambio de contraseña, cuota, rutinas, avisos y reservas/cancelaciones semanales.

El alta de alumnos usa su DNI como usuario y contraseña inicial. El alumno puede cambiar la contraseña.
Las imágenes de avisos se almacenan en MySQL y se consultan con sesión iniciada.

## Verificación

Las pruebas en tests se ejecutan con PHP CLI y requieren MySQL y Apache en el puerto 8080.
Usan datos temporales que retiran al finalizar. No son necesarias para servir la aplicación.
Los scripts integracion.php y alumnos-datos.php contienen flujos anteriores al acceso automático con DNI y requieren adaptación antes de utilizarlos como validación completa.

Se retiraron las páginas y scripts de demostración que no usa el sistema. La base de datos y los módulos funcionales se conservaron.
