# Notas históricas de desarrollo

Estas notas pueden estar desactualizadas. Consultar README.md en la raíz para el estado actual.

# Sistema Gimnasio

PHP 8.2, MySQL/MariaDB y SB Admin. Solo Administrador y Alumno.
Inicio de sesión con usuario y contraseña.

## Acceso
Iniciar Apache y MySQL en XAMPP:
http://localhost:8080/sistema-gym/

## Instalación
Revisar config.php y ejecutar:

    C:\xampp\php\php.exe C:\xampp\htdocs\sistema-gym\instalar.php

El instalador crea las tablas y solicita los datos de una cuenta.
Para actualizar el esquema de una instalación existente:

    C:\xampp\php\php.exe C:\xampp\htdocs\sistema-gym\actualizar.php

La actualización ya se aplicó en este equipo.

## Cuotas
- La sección muestra únicamente Alumno y Estado de la cuota.
- Listado de todos los alumnos ordenado por nombre de la A a la Z, con
  ordenación española que contempla acentos. Incluye alumnos activos e inactivos.
- Estados: Al día y Vencida.
- El administrador actualiza Último mes abonado desde Alumnos > Editar.
- Las cuotas vencen el día 10 inclusive de cada mes. Desde el 11 se exige
  que esté abonado el mes actual.
- Durante los días 1 al 10, haber abonado el mes anterior mantiene la cuota
  al día. Una deuda más antigua continúa vencida.
- Un último mes abonado posterior también cubre los meses previos.
- Sin mes abonado registrado, el estado es Vencida.
- El alumno consulta únicamente su estado, sin ver otros alumnos.
- Cuotas es de solo consulta: no contiene formularios, importes ni historial
  de cobros. El estado no bloquea automáticamente el acceso a la cuenta.

Ejemplo: septiembre abonado mantiene la cuota al día hasta el 10 de octubre;
el 11 de octubre figura vencida si octubre no está abonado.

## Secciones
- Dashboard: alumnos activos, días con rutina publicada, horarios del gimnasio y cupos disponibles;
  resumen de cuotas.
- Alumnos: alta, búsqueda, edición, baja lógica y reactivación. Datos personales,
  último mes abonado, estado de cuota y horario habitual.
- Usuarios: crear cuentas de los dos roles, editar nombre/usuario, restablecer
  contraseña y activar/desactivar. No se puede desactivar la propia cuenta
  ni dejar al sistema sin administrador activo. El rol existente se conserva.
- Rutinas: una rutina única para cada día de lunes a sábado. El administrador
  elige el día y escribe en un área de texto tipo bloc de notas. Guardar publica
  el texto para todos los alumnos, conservando los saltos de línea.
  El contenido se mantiene cada semana hasta que se edita; no se asigna por alumno.
  Se abre el día actual por defecto y se puede consultar cualquier otro día.
  Guardar vacío deja ese día sin rutina. Máximo: 20.000 caracteres por día.

- Clases: listado de los horarios 07:00, 08:00, 13:00, 14:00, 15:00,
  16:00, 17:00, 18:00, 19:00 y 20:00, con los alumnos de cada grupo
  ordenados alfabéticamente. Cada horario admite 20 alumnos activos.
- Horario habitual: obligatorio al crear un alumno desde Alumnos, Usuarios
  o el instalador. Se cambia desde la ficha del alumno o su cuenta.
  El alumno consulta su propio horario sin acceder a los nombres de otros.
- Los alumnos anteriores sin horario aparecen como pendientes de asignación
  en Clases; el administrador elige el horario real desde Editar.

## Reglas
- Zona horaria: America/Argentina/Buenos_Aires.
- Todos los alumnos ven la misma rutina semanal, sin asignaciones individuales.
- Los diez horarios son grupos habituales; no se crean eventos por fecha.
- Cambiar de horario libera el lugar anterior y valida el cupo del nuevo.
- Desactivar un alumno libera su lugar. Al reactivarlo se vuelve a validar el cupo.
- Las cuentas inactivas pierden acceso en la siguiente petición.
- Recuperación de contraseña mediante el administrador, desde Usuarios.

## Pruebas
Con Apache y MySQL iniciados:

    C:\xampp\php\php.exe C:\xampp\htdocs\sistema-gym\tests\integracion.php

Crea registros temporales con prefijo único y los elimina al terminar.
Verifica cuentas, cuotas (día 10/11, cambio de año y deuda anterior),
orden alfabético, permisos, privacidad, rutinas semanales y cupos concurrentes. Comprueba
el límite de 20 al crear, editar o reactivar cuentas de alumno.
No modifica las cuentas existentes.

## Organización
- bootstrap.php: conexión y autenticación.
- schema.php / actualizar.php: actualización del esquema.
- domain.php: reglas, validaciones y operaciones transaccionales.
- module.php: gestión compartida de formularios y permisos.
- layout.php: SB Admin; css/gym.css: ajustes móviles.
- cuotas.php: listado de solo consulta.
- tests/: pruebas de integración bloqueadas por HTTP.

Las páginas PHP son las pantallas funcionales; los HTML originales son ejemplos
de la plantilla. Configuración preparada para XAMPP local; para Internet se
requieren HTTPS, configuración de producción y límites de intentos de login.

Para probar rutinas, la prueba utiliza un día sin contenido y lo restaura al
terminar. Si todos los días ya tienen rutina, no reemplaza contenido real.
Las rutinas muestran el día y su fecha, por ejemplo “Lunes 12”.
Las fechas corresponden a la semana actual en Argentina; los domingos se
muestra la próxima semana. Se actualizan automáticamente con la página abierta,
sin recargar ni borrar lo que se esté escribiendo. La rutina semanal se conserva
hasta que el administrador la modifica; la fecha no crea un historial diario.

Datos de alumnos: nombre, apellido, fecha de nacimiento, DNI único (7 u 8 dígitos), teléfono y correo electrónico son obligatorios al guardar la ficha. El correo es de contacto; el acceso conserva usuario y contraseña. Ejecutar `php actualizar.php` para agregar las columnas sin borrar datos. Las fichas anteriores quedan pendientes de completar, sin inferir DNI ni apellido.


Acceso inicial de alumnos: las altas desde Alumnos, Usuarios y el instalador usan el DNI como usuario y contraseña inicial. Las cuentas existentes no se modifican. En Mi perfil, el alumno puede elegir Cambiar mi contraseña, ingresando la actual y confirmando la nueva. No se exige una longitud mínima ni un máximo de caracteres, pero la contraseña no puede estar vacía. Los nuevos hashes usan Argon2id para evitar el truncamiento de bcrypt a 72 bytes.


Horarios: el administrador puede agregar turnos, editar su hora y cupo, o eliminar turnos sin alumnos asignados. Al cambiar la hora se conservan las asignaciones, incluidas las de alumnos inactivos. No se permite reducir el cupo por debajo de los alumnos activos. Clases, Dashboard y altas usan esta configuración. La migración conserva los turnos existentes con cupo inicial 20 y no vuelve a crear turnos eliminados.

Dashboard del alumno: alumno.php saluda al alumno y muestra cuota, rutina de hoy, horario y avisos publicados. Los datos personales y el cambio de contraseña siguen disponibles desde Mi perfil (perfil.php). El administrador gestiona Avisos desde avisos.php: texto libre de hasta 20.000 caracteres e imagen opcional JPG/PNG/WebP de hasta 2 MB. Las imágenes se guardan en MySQL y se sirven únicamente a usuarios autenticados. Los avisos pueden editarse o eliminarse; después de publicar se limpia el bloc. Ejecutar php actualizar.php para crear la tabla en otras instalaciones. Pruebas: php tests/avisos.php.

Mis clases: cada alumno tiene una clase diaria de lunes a sábado en su horario habitual. Puede consultar la semana actual y la siguiente, cancelar o cambiar una clase antes de su inicio. Los cambios por fecha se guardan en reservas_diarias sin modificar el horario habitual; una fila con hora nula representa una cancelación. El cupo diario incluye asignaciones habituales y excepciones. Las reservas se serializan con el bloqueo de turnos para evitar sobrecupos. El administrador consulta Clases por fecha. La baja definitiva del alumno elimina sus excepciones. Pruebas: php tests/reservas.php.
