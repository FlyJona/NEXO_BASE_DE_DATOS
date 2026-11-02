# Scripts de PowerShell

Funcionan en **Windows PowerShell 5.1** (el que trae Windows) y en **PowerShell 7**. Se ejecutan desde la raíz del repositorio:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply_migrations.ps1
```

Ninguno muestra contraseñas, borra datos ni elimina volúmenes de Docker.
`_comun.ps1` contiene funciones compartidas y no se ejecuta solo.

## `new_env.ps1`

Crea `.env` (a partir de `.env.example`) con contraseñas aleatorias de 32 caracteres y un secreto JWT de 48, y crea
`nexo/config/config.local.php` para que la app PHP use PostgreSQL con el rol `nexo_app`. **Nunca sobrescribe** archivos existentes.

| Parámetros | Requisitos | Salida |
|---|---|---|
| — | Ninguno | `0` creado o ya existía |

## `apply_migrations.ps1`

Aplica en orden las migraciones pendientes. Detalle en [`migrations/README.md`](../migrations/README.md).

| Parámetro | Por defecto | Uso |
|---|---|---|
| `-Database` | `POSTGRES_DB` de `.env` | Base destino |
| `-MigrationsPath` | `migrations/` | Carpeta de migraciones |
| `-User` | `nexo_migrator` | Rol que ejecuta |
| `-DryRun` | — | Solo lista las pendientes |

Requisitos: `.env`, Docker Desktop en marcha y el servicio `db` en estado `healthy`.

| Código | Significado |
|---|---|
| `0` | Correcto (o nada pendiente) |
| `1` | Una migración falló; se revirtió y se anotó en `nexo_meta.migration_failures` |
| `2` | Faltan requisitos: `.env`, Docker, base no lista o inexistente |
| `3` | Migraciones inválidas: nombre, versión repetida, `BEGIN/COMMIT` propio, archivo aplicado modificado o borrado |

## `check_connection.ps1`

Distingue "iniciado" de "listo". Comprueba:

1. `.env`: variables definidas (sin mostrar su valor), secreto JWT de 32 caracteres o más, contraseñas válidas para URL.
2. `docker compose` disponible y `compose.yaml` coherente con `.env` (`docker compose config`).
3. PostgreSQL: contenedor creado, healthcheck `healthy`, consulta como `nexo_migrator`, **conexión con contraseña como
   `nexo_app`** (igual que PHP), historial legible y puerto `127.0.0.1:5432` abierto.
4. PostgREST: contenedor en marcha, `/live` (proceso vivo), `/ready` (conectado a PostgreSQL con el esquema cargado) y `GET /materias`.

| Código | Significado |
|---|---|
| `0` | Todo listo |
| `1` | Alguna comprobación falló (se listan) |
| `2` | No hay `.env` o Docker no está disponible |

## `new_jwt.ps1`

Genera un JWT HS256 firmado con `PGRST_JWT_SECRET` para probar la API.

| Parámetro | Por defecto | Uso |
|---|---|---|
| `-Role` | `nexo_lector` | `nexo_lector` o `web_anon` |
| `-Minutes` | `60` | Vigencia (1 a 1440) |

Salida: imprime solo el token. Código `2` si falta el secreto.
