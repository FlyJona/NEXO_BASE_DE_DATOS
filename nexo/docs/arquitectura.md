# NEXO – Arquitectura y modelo de datos (Sprint 1)

Paquete de la EDT: **1.3.1 Diseñar la arquitectura y el modelo de datos**.

## Arquitectura

NEXO es una aplicación web de tres capas, con las herramientas definidas en el Form 7:

| Capa | Tecnología | Archivos |
|---|---|---|
| Presentación | HTML5, CSS3, JavaScript, Bootstrap 5 (CDN), highlight.js para los bloques de código | `*.php` de la raíz, `includes/encabezado.php`, `includes/pie.php`, `assets/` |
| Lógica | PHP 8 (sin framework) | `includes/*.php` |
| Datos | MySQL (phpMyAdmin) | `database/nexo.sql` |

Entornos: **XAMPP** en local y **InfinityFree** para las demostraciones. El código se guarda en **GitHub**.

```
Navegador ──HTTP──> Apache (XAMPP / InfinityFree)
                      │
                      ├─ páginas PHP (index, registro, login, perfil, publicar, publicacion…)
                      │     └─ includes/ (sesión, permisos, validaciones, adjuntos)
                      │
                      ├─ MySQL  (usuarios, perfiles, publicaciones, comentarios…)
                      └─ uploads/ (imágenes y archivos; no se accede directo, solo por adjunto.php)
```

### Estructura de carpetas

```
nexo/
├─ index.php                 Inicio: publicaciones recientes (filtro por materia y tipo)
├─ registro.php              PBI-01 Registro con correo institucional
├─ login.php / logout.php    PBI-01 Inicio y cierre de sesión
├─ recuperar.php             PBI-01 Solicitar recuperación de contraseña
├─ restablecer.php           PBI-01 Nueva contraseña con enlace de un solo uso
├─ usuarios.php              PBI-01 Asignar roles (solo moderador)
├─ perfil.php                PBI-02 Consultar perfil
├─ perfil_editar.php         PBI-02 Crear / editar perfil
├─ estudiantes.php           PBI-02 Buscar estudiantes por materia o interés
├─ publicar.php              PBI-03 Crear / editar publicaciones (imágenes, archivos, código)
├─ publicacion.php           PBI-03/04 Ver publicación, comentar, votar, guardar
├─ acciones.php              PBI-03/04 Acciones (votar, guardar, comentar, visibilidad, eliminar)
├─ adjunto.php               Entrega segura de imágenes y archivos
├─ instalar.php              Crea los usuarios y datos de prueba (solo en desarrollo)
├─ assets/                   nexo.css, nexo.js
├─ config/config.php         Conexión a la BD, dominio de correo, límites
├─ includes/                 Funciones comunes (no accesibles desde el navegador)
├─ database/nexo.sql         Esquema y catálogo de materias
├─ docs/                     Este documento y el diagrama del modelo de datos
└─ uploads/                  Adjuntos subidos (protegidos)
```

## Roles y permisos (TR-01)

| Permiso | Estudiante | Docente | Moderador |
|---|:-:|:-:|:-:|
| Leer, votar, guardar | ✔ | ✔ | ✔ |
| Comentar y publicar (normal o resguardado) | ✔ | ✔ | ✔ |
| Editar / eliminar lo propio | ✔ | ✔ | ✔ |
| Ocultar o eliminar publicaciones de otros | | | ✔ |
| Ver el nombre real detrás de una publicación resguardada | | | ✔ |
| Asignar roles y desactivar cuentas | | | ✔ |

Todos se registran como **estudiante**; un moderador asigna el rol de docente o moderador desde `usuarios.php`.

## Participación escalonada (RC-08)

Niveles de exposición, de menor a mayor: **leer → guardar o votar → comentar → preguntar o publicar**.
En cualquier nivel con texto (comentario o publicación) se puede elegir **modo resguardado**: los demás ven
"Estudiante resguardado" en lugar del nombre. El autor puede cambiar la visibilidad después
y los moderadores siempre ven el nombre real para poder atender reportes.

## Modelo de datos

Diagramas editables (abrir en draw.io / diagrams.net):

- [`diagrama_entidad_relacion.drawio`](diagrama_entidad_relacion.drawio): modelo conceptual en notación de Chen
  (entidades, relaciones, atributos, llaves y cardinalidades 1:1, 1:N y N:M).
- [`modelo_relacional.drawio`](modelo_relacional.drawio): tablas con columnas, tipos de dato MySQL, PK, FK y
  relaciones en notación pata de gallo.

| Tabla | Propósito | Relaciones |
|---|---|---|
| `usuarios` | Cuenta, correo institucional, contraseña (hash), rol, activo | 1–1 `perfiles`; 1–N `publicaciones`, `comentarios` |
| `perfiles` | Carrera, semestre, biografía, habilidades, intereses | N–1 `usuarios` |
| `materias` | Taxonomía de materias por carrera y semestre | N–M `usuarios` vía `usuario_materias` |
| `usuario_materias` | Materias cursadas por cada usuario | — |
| `recuperaciones` | Tokens (hash) de recuperación de contraseña con caducidad | N–1 `usuarios` |
| `publicaciones` | Contenido o pregunta, materia, resguardada, estado (visible/oculta) | N–1 `usuarios`, `materias` |
| `adjuntos` | Imágenes y archivos de cada publicación | N–1 `publicaciones` |
| `comentarios` | Respuestas a publicaciones (pueden ser resguardadas) | N–1 `publicaciones`, `usuarios` |
| `votos` | Voto "útil" de un usuario a una publicación | N–M |
| `guardados` | Publicaciones guardadas por cada usuario (base del panel del PBI-05) | N–M |

Tablas previstas para Sprints siguientes (no creadas todavía): `reportes` y marca de respuesta verificada
(PBI-08), marca de duda resuelta y notificaciones (PBI-07).

## Seguridad aplicada

- Contraseñas con `password_hash` (bcrypt); tokens de recuperación guardados como hash SHA-256, válidos 1 hora y de un solo uso.
- Consultas preparadas con PDO (sin SQL armado con datos del usuario).
- Token CSRF en todos los formularios que modifican datos.
- Toda salida escapada con `htmlspecialchars`.
- Adjuntos: extensión y tipo MIME validados, nombre aleatorio, máximo 5 MB y 5 por publicación; la carpeta `uploads/` no es accesible directo.
- Cookie de sesión `HttpOnly` y `SameSite=Lax`; el id de sesión se regenera al iniciar sesión.
