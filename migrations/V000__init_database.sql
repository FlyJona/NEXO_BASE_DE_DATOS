-- V000: elementos iniciales de la base de datos.
-- Se ejecuta como nexo_migrator, dueño de la base. La base y los roles NO se crean aquí:
-- los crea docker/postgres/init/01_roles.sh la primera vez que arranca el contenedor.

-- unaccent: búsqueda sin distinguir acentos, como la intercalación utf8mb4_unicode_ci de MySQL
-- ("robotica" encuentra "robótica"). Es una extensión "trusted": la puede instalar el dueño de la base.
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;

-- nexo: tablas de la aplicación. api: lo que publica PostgREST (solo vistas).
CREATE SCHEMA IF NOT EXISTS nexo AUTHORIZATION nexo_migrator;
CREATE SCHEMA IF NOT EXISTS api AUTHORIZATION nexo_migrator;

COMMENT ON SCHEMA nexo IS 'Tablas de NEXO. Las usa la app PHP con el rol nexo_app.';
COMMENT ON SCHEMA api IS 'Vistas de solo lectura publicadas por PostgREST.';
