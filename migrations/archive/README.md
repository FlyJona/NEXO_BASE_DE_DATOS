# Archivo de esquemas

Aquí van los scripts de esquema que ya no se aplican con `apply_migrations.ps1`. Esta carpeta no se lee al migrar.

## Esquema MySQL de origen

El esquema MySQL original **no se movió aquí** porque sigue en uso: la app PHP funciona con MySQL cambiando
`DB_MOTOR` a `mysql` en `nexo/config/config.local.php`, que es lo que necesita InfinityFree (no ofrece PostgreSQL).

- Archivo: [`nexo/database/nexo.sql`](../../nexo/database/nexo.sql)
- Equivalencia con PostgreSQL: [`nexo/docs/auditoria_mysql_postgresql.md`](../../nexo/docs/auditoria_mysql_postgresql.md)

Si el equipo decide dejar MySQL por completo, ese archivo se mueve aquí y se quita la rama `mysql` de `nexo/includes/db.php`.
