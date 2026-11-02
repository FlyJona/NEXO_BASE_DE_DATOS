# NEXO – Comunidad estudiantil digital (MVP)

Código de los **Sprints 1 y 2** del proyecto NEXO (TESH).
Tecnologías: HTML5, CSS3, JavaScript, Bootstrap 5, PHP 8, PostgreSQL (Docker) o MySQL, XAMPP, Git/GitHub, InfinityFree.

## Qué incluye

| Sprint | PBI | Requisito | Funcionalidad | Archivos |
|---|---|---|---|---|
| 1 | — | 1.3.1 | Arquitectura, diagrama entidad-relación y modelo relacional | `docs/arquitectura.md`, `docs/diagrama_entidad_relacion.drawio`, `docs/modelo_relacional.drawio`, `database/nexo.sql` |
| 1 | — | 1.3.3 | Taxonomía de materias (ejemplo ISC, **validar con el plan del TESH**) | `database/nexo.sql` |
| 1 | PBI-01 | TR-01 | Registro solo con correo institucional, inicio y cierre de sesión, recuperar contraseña, roles y permisos | `registro.php`, `login.php`, `logout.php`, `recuperar.php`, `restablecer.php`, `usuarios.php`, `includes/auth.php` |
| 1 | PBI-02 | RC-05 | Perfil con materias, habilidades e intereses; búsqueda de estudiantes por materia o interés | `perfil.php`, `perfil_editar.php`, `estudiantes.php` |
| 2 | PBI-03 | RC-11 | Editor de publicaciones con título, imágenes, archivos y bloques de código; editar y eliminar | `publicar.php`, `publicacion.php`, `adjunto.php`, `includes/adjuntos.php` |
| 2 | PBI-04 | RC-08 | Participación escalonada (leer → votar/guardar → responder → preguntar) y publicación/respuesta resguardada; cambiar visibilidad | `publicacion.php`, `acciones.php`, `index.php` |
| 2 | — | 1.3.2 | Pantallas (implementadas directamente en HTML + Bootstrap) | todas las páginas |

## Base de datos: PostgreSQL o MySQL

La app funciona con los dos motores; se elige con `DB_MOTOR` en `config/config.local.php`.

- **PostgreSQL (por defecto, en local):** corre en Docker. Sigue la [guía del README raíz](../README.md#puesta-en-marcha):
  `scripts/new_env.ps1` crea `config.local.php` con `DB_MOTOR = 'pgsql'`. En `C:\xampp\php\php.ini` quita el `;` de
  `;extension=pdo_pgsql` y reinicia Apache.
- **MySQL (XAMPP o InfinityFree):** pon `define('DB_MOTOR', 'mysql');` y los datos de MySQL en `config.local.php` e importa
  `database/nexo.sql`.

Equivalencias entre ambos esquemas: [`docs/auditoria_mysql_postgresql.md`](docs/auditoria_mysql_postgresql.md).

## Instalación en XAMPP con MySQL (cada integrante)

1. Instala **XAMPP** (con PHP 8) y abre el *XAMPP Control Panel*. Inicia **Apache** y **MySQL**.
2. Copia la carpeta `nexo` dentro de `C:\xampp\htdocs\` (queda `C:\xampp\htdocs\nexo`).
3. Crea `config/config.local.php` con `<?php define('DB_MOTOR', 'mysql');` (usuario `root` sin contraseña por defecto).
4. Abre `http://localhost/phpmyadmin` → pestaña **Importar** → elige `nexo/database/nexo.sql` → **Continuar**.
   Se crea la base `nexo` con sus tablas y el catálogo de materias.
5. Abre `http://localhost/nexo/instalar.php` y pulsa **Crear datos de prueba**.
6. Entra a `http://localhost/nexo` con alguna de las cuentas de prueba.

Con PostgreSQL los pasos 1 a 4 se sustituyen por la puesta en marcha del README raíz; el paso 5 es igual.

### Cuentas de prueba (datos ficticios)

Contraseña de todas: `NexoPrueba2026` (constante `CONTRASENA_PRUEBA` en `instalar.php`).

| Correo | Rol |
|---|---|
| `moderador@tesh.edu.mx` | Moderador |
| `docente@tesh.edu.mx` | Docente |
| `ana.prueba@tesh.edu.mx` | Estudiante |
| `luis.prueba@tesh.edu.mx` | Estudiante |

> El dominio sale de `DOMINIO_CORREO` en `config/config.php`. Si lo cambias, las cuentas de prueba usan el nuevo dominio.

### Recuperar contraseña en XAMPP

XAMPP no envía correos. En modo `desarrollo` el enlace de recuperación aparece en pantalla.

## Configuración

Todo está en `config/config.php`:

- `DOMINIO_CORREO`: **pendiente de confirmar con Sistemas / TI**. Por ahora `tesh.edu.mx` como dominio de prueba (premisa del Form 4).
- `APP_URL`: dirección donde corre NEXO (`http://localhost/nexo` en XAMPP).
- `DB_*`: conexión a MySQL.

Para InfinityFree (u otro entorno) no edites `config.php`: crea `config/config.local.php` con tus valores; ese archivo no se sube a GitHub.

```php
<?php
define('APP_ENTORNO', 'produccion');
define('APP_URL', 'https://tu-sitio.infinityfreeapp.com');
define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_NOMBRE', 'if0_XXXXXXX_nexo');
define('DB_USUARIO', 'if0_XXXXXXX');
define('DB_CONTRASENA', 'la-contraseña-de-tu-panel');
```

## Subir a InfinityFree

1. Crea la base en el panel de InfinityFree y abre su phpMyAdmin.
2. Borra las líneas `CREATE DATABASE` y `USE nexo` de `nexo.sql` e impórtalo.
3. Sube el contenido de la carpeta `nexo` a `htdocs/` (FileZilla o el administrador de archivos).
4. Crea `config/config.local.php` como se muestra arriba.
5. Crea las cuentas reales registrándote; para el primer moderador cambia su rol en phpMyAdmin (`usuarios.rol = 'moderador'`).
6. **Borra `instalar.php`** del servidor.

> InfinityFree no permite enviar correos con `mail()`: la recuperación de contraseña por correo solo funcionará
> en un hosting que lo permita (o configurando SMTP en un Sprint posterior).

## GitHub

```bash
git init
git add .
git commit -m "Sprint 1 y 2: acceso, perfiles, publicaciones y participación resguardada"
```

Después crea el repositorio en GitHub, agrega a los tres integrantes y haz `git push`.

## Lo que el equipo debe hacer (no se puede hacer con código)

- [ ] Instalar XAMPP y VS Code en las 3 computadoras y probar el flujo completo.
- [ ] Crear el repositorio en GitHub y la cuenta de InfinityFree.
- [ ] Revisar en equipo el diagrama E-R y el modelo relacional (`docs/*.drawio`, se abren en draw.io).
- [ ] Sustituir el catálogo de materias de ejemplo por el plan de estudios real del TESH (y otras carreras).
- [ ] Pedir a Sistemas / TI el dominio de correo institucional por escrito (vencía el 26/10).
- [ ] Designar al patrocinador y firmar el acta de constitución (vencía el 26/10).
- [ ] Hacer la prueba de uso con 3 a 5 estudiantes (publicación resguardada) y anotar los comentarios en el Product Backlog.
- [ ] Realizar la Sprint Review y la Retrospectiva de los Sprints 1 y 2 con el asesor.
- [ ] Medir que registrarse y publicar la primera duda tome 3 minutos o menos.

## Siguiente: Sprint 3 (al 04/11/2026)

- PBI-05 Búsqueda interna (ya existe el índice `FULLTEXT` en `publicaciones`) y panel de guardados (ya existe la tabla `guardados`).
- PBI-06 Tablero de preguntas por materia (ya existe el tipo `pregunta` y el filtro por materia en el inicio).
