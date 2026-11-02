# Migraciones de PostgreSQL

Cada archivo cambia la base de datos un paso. Se aplican con [`scripts/apply_migrations.ps1`](../scripts/README.md).

## Convención de nombres

`VNNN__descripcion_en_minusculas.sql` — tres dígitos y **dos** guiones bajos. Ejemplo: `V005__reportes_de_contenido.sql`.
El script rechaza cualquier otro nombre (código de salida 3).

| Archivo | Contenido |
|---|---|
| `V000__init_database.sql` | Extensión `unaccent` y esquemas `nexo` (tablas) y `api` (PostgREST) |
| `V001__baseline_schema.sql` | Las 10 tablas traducidas de MySQL, con llaves, restricciones e índices |
| `V002__triggers.sql` | Trigger `actualizado_en` (equivalente de `ON UPDATE CURRENT_TIMESTAMP`) |
| `V003__seed_data.sql` | Catálogo de 38 materias (idempotente) |
| `V004__api_y_permisos.sql` | Permisos de `nexo_app`, vistas `api.*` y permisos de la API |

La base y los roles **no** se crean aquí: los crea [`docker/postgres/init/01_roles.sh`](../docker/postgres/init/01_roles.sh)
la primera vez que arranca el contenedor (necesita el superusuario y las contraseñas de `.env`).

## Orden y ejecución

- Se aplican por número, de menor a mayor, como `nexo_migrator` (dueño de la base).
- Cada migración corre en **una sola transacción** junto con su registro en el historial. Si algo falla, se revierte
  todo, la migración **no** queda marcada y el script se detiene (código 1).
- Por eso una migración **no** debe incluir `BEGIN`, `COMMIT` ni `ROLLBACK` (el script la rechaza) ni sentencias que
  no admiten transacción (`CREATE INDEX CONCURRENTLY`, `VACUUM`, `CREATE DATABASE`).
- Usa `SET LOCAL search_path TO nexo, public;` al inicio para crear objetos en el esquema `nexo`.

## Historial

Esquema `nexo_meta` (el script lo crea si no existe):

| Tabla | Columnas | Para qué |
|---|---|---|
| `schema_migrations` | `version` (PK), `descripcion`, `archivo`, `checksum` (SHA-256), `aplicada_en`, `aplicada_por`, `duracion_ms` | Migraciones aplicadas con éxito |
| `migration_failures` | `id`, `version`, `archivo`, `checksum`, `fallo_en`, `error` | Intentos fallidos (la migración no quedó aplicada) |

```sql
SELECT version, archivo, aplicada_en, duracion_ms FROM nexo_meta.schema_migrations ORDER BY version;
SELECT * FROM nexo_meta.migration_failures ORDER BY fallo_en DESC;
```

El checksum se calcula con saltos de línea normalizados a LF, así que es el mismo en Windows y Linux.

## Crear una migración nueva

1. Crea el archivo con el siguiente número libre: `migrations/V005__que_hace.sql`.
2. Si crea tablas, agrega en la misma migración los `GRANT` para `nexo_app` (y para la API si aplica).
3. Pruébala en una base temporal: `.\tests\run_tests.ps1` (aplica todo desde cero).
4. Aplícala: `.\scripts\apply_migrations.ps1`.

**Nunca edites una migración ya aplicada.** El script compara checksums y se detiene (código 3). Para corregir algo, crea otra migración.

## Recuperación ante errores

| Situación | Qué hacer |
|---|---|
| Una migración falló (código 1) | No se aplicó nada de ella. Lee el error, corrige **ese** archivo (aún no está aplicado) y vuelve a ejecutar el script |
| "cambió después de aplicarse" (código 3) | Restaura el archivo original con `git checkout -- migrations/VNNN__...sql` y pon el cambio en una migración nueva |
| "aplicada pero su archivo no existe" (código 3) | Recupera el archivo borrado desde Git |
| Hay que deshacer una migración aplicada | No hay "down" automático: escribe una migración nueva que revierta el cambio, o restaura una copia de seguridad (ver README raíz) |

## `archive/`

Esquemas históricos que ya no se aplican. El esquema MySQL de origen **sigue vigente** para hosting sin PostgreSQL
(InfinityFree) y por eso permanece en [`nexo/database/nexo.sql`](../nexo/database/nexo.sql); ver [`archive/README.md`](archive/README.md).
