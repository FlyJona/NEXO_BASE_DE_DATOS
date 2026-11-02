# Pruebas

## `run_tests.ps1` — base de datos y API

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\run_tests.ps1
powershell -ExecutionPolicy Bypass -File .\tests\run_tests.ps1 -IncluirReinicio   # también prueba persistencia
```

Requisitos: `.env`, `docker compose up -d` y las migraciones aplicadas a la base `nexo`.

**No toca la base `nexo`:** las pruebas de esquema crean una base temporal `nexo_prueba_XXXX` y la borran al final.

| Conjunto | Qué valida |
|---|---|
| Migraciones | Aplica V000–V004 en orden desde una base vacía; una segunda ejecución no aplica nada; la semilla no duplica materias |
| Errores del script | Una migración con error se revierte entera, no se marca y se anota en `migration_failures` (exit 1); detecta una migración aplicada modificada (exit 3); rechaza nombres inválidos y `BEGIN/COMMIT` propios (exit 3); base inexistente (exit 2) |
| `sql/01_esquema.sql` | 10 tablas, 13 llaves foráneas con índice, identidades, booleanos, índice GIN, 2 triggers, 38 materias, 3 vistas, 5 migraciones |
| `sql/02_restricciones.sql` | Correo único y en minúsculas, rol/tipo válidos, semestre 1..12, materia única sin mayúsculas, FK, `CASCADE`, `SET NULL`, voto único, trigger `actualizado_en` |
| `sql/03_permisos.sql` | `nexo_app` solo hace lo que la app necesita (sin DDL, sin borrar usuarios, sin escribir el catálogo); `web_anon` solo ve materias; `nexo_lector` solo ve vistas; `authenticator` no hereda permisos; la vista oculta al autor resguardado |
| PostgREST (solo lectura sobre `nexo`) | Anónimo: `/materias` 200 con 38 filas, `/publicaciones` 401, `POST` denegado; `/usuarios` no existe (404); con JWT `nexo_lector`: `/publicaciones` 200, `POST` denegado; firma falsa 401 |
| `-IncluirReinicio` | `docker compose restart` (no borra volúmenes) y comprueba que datos, historial y API siguen |

Los archivos SQL de `sql/` revierten todo lo que hacen (`BEGIN ... ROLLBACK`).

## `app_e2e.ps1` — app PHP contra la base configurada

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\app_e2e.ps1
```

Requisitos: Apache de XAMPP encendido con `pdo_pgsql` habilitado, `nexo/config/config.local.php` apuntando a PostgreSQL y
los datos de prueba creados con `http://localhost/nexo/instalar.php`.

Recorre los flujos del Sprint 1 y 2: inicio de sesión, publicación resguardada con código y adjunto, voto, guardado,
respuesta, permisos, moderación, perfil, búsqueda sin mayúsculas ni acentos y recuperación de contraseña.
Borra la publicación que crea. Sirve igual con `DB_MOTOR` en `mysql` para comprobar que MySQL sigue funcionando.

| Código | Significado |
|---|---|
| `0` | Todo correcto |
| `1` | Alguna comprobación falló |
| `2` | La app no responde |
