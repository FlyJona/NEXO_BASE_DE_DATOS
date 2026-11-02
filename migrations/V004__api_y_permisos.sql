-- V004: permisos de mínimo privilegio y vistas de la API (PostgREST).
-- Los roles los crea docker/postgres/init/01_roles.sh (necesita superusuario y contraseñas de .env):
--   nexo_migrator  LOGIN     dueño de la base y de todos los objetos; ejecuta las migraciones.
--   nexo_app       LOGIN     la app PHP; solo DML en las tablas que usa, sin DDL.
--   authenticator  LOGIN     conexión de PostgREST (NOINHERIT); solo puede cambiar a web_anon o nexo_lector.
--   web_anon       NOLOGIN   peticiones sin token: solo el catálogo de materias.
--   nexo_lector    NOLOGIN   peticiones con JWT {"role":"nexo_lector"}: lectura de contenido visible.
-- La API es de SOLO LECTURA: las escrituras siguen pasando por la app PHP, que valida y aplica las reglas.

SET LOCAL search_path TO nexo, public;

DO $$
DECLARE
    rol text;
BEGIN
    FOREACH rol IN ARRAY ARRAY['nexo_app', 'authenticator', 'web_anon', 'nexo_lector'] LOOP
        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = rol) THEN
            RAISE EXCEPTION 'Falta el rol %. Se crea con docker/postgres/init/01_roles.sh al iniciar un volumen nuevo.', rol;
        END IF;
    END LOOP;
    -- Solo los roles de NEXO pueden conectarse a esta base.
    EXECUTE format('REVOKE CONNECT, TEMPORARY ON DATABASE %I FROM PUBLIC', current_database());
    EXECUTE format('GRANT CONNECT ON DATABASE %I TO nexo_app, authenticator', current_database());
END;
$$;

-- Nadie más que el dueño crea objetos en public; el resto solo usa unaccent.
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

-- ---------------------------------------------------------------
-- App PHP (nexo_app): exactamente las operaciones que hace el código
-- ---------------------------------------------------------------
REVOKE ALL ON SCHEMA nexo FROM PUBLIC;
GRANT USAGE ON SCHEMA nexo TO nexo_app;

GRANT SELECT, INSERT, UPDATE ON usuarios, perfiles, recuperaciones TO nexo_app;
GRANT SELECT ON materias TO nexo_app;
GRANT SELECT, INSERT, DELETE ON usuario_materias, adjuntos, comentarios, votos, guardados TO nexo_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON publicaciones TO nexo_app;
-- lastInsertId() de PDO usa lastval(), que pide USAGE/SELECT sobre la secuencia de identidad.
GRANT USAGE, SELECT ON SEQUENCE
    usuarios_id_seq, recuperaciones_id_seq, publicaciones_id_seq, adjuntos_id_seq, comentarios_id_seq
    TO nexo_app;

-- ---------------------------------------------------------------
-- API (PostgREST): vistas en el esquema api; las tablas de nexo no se exponen
-- ---------------------------------------------------------------
REVOKE ALL ON SCHEMA api FROM PUBLIC;
GRANT USAGE ON SCHEMA api TO web_anon, nexo_lector;

CREATE OR REPLACE VIEW api.materias WITH (security_barrier = true) AS
SELECT id, nombre, carrera, semestre
FROM nexo.materias;
COMMENT ON VIEW api.materias IS 'Catálogo público de materias.';

-- Solo publicaciones visibles. Si es resguardada se ocultan el autor y su id (RC-08).
-- Sin correos, contraseñas ni adjuntos (los archivos solo se entregan con sesión en la app).
CREATE OR REPLACE VIEW api.publicaciones WITH (security_barrier = true) AS
SELECT p.id,
       p.tipo,
       p.titulo,
       p.contenido,
       p.materia_id,
       m.nombre AS materia,
       CASE WHEN p.resguardada THEN NULL ELSE p.usuario_id END AS autor_id,
       CASE WHEN p.resguardada THEN 'Estudiante resguardado' ELSE u.nombre END AS autor,
       p.resguardada,
       p.creado_en,
       (SELECT count(*) FROM nexo.votos v WHERE v.publicacion_id = p.id) AS total_votos,
       (SELECT count(*) FROM nexo.comentarios c WHERE c.publicacion_id = p.id) AS total_comentarios
FROM nexo.publicaciones p
JOIN nexo.usuarios u ON u.id = p.usuario_id
LEFT JOIN nexo.materias m ON m.id = p.materia_id
WHERE p.estado = 'visible';
COMMENT ON VIEW api.publicaciones IS 'Publicaciones visibles; requiere JWT con rol nexo_lector.';

CREATE OR REPLACE VIEW api.comentarios WITH (security_barrier = true) AS
SELECT c.id,
       c.publicacion_id,
       c.contenido,
       CASE WHEN c.resguardado THEN NULL ELSE c.usuario_id END AS autor_id,
       CASE WHEN c.resguardado THEN 'Estudiante resguardado' ELSE u.nombre END AS autor,
       c.resguardado,
       c.creado_en
FROM nexo.comentarios c
JOIN nexo.usuarios u ON u.id = c.usuario_id
JOIN nexo.publicaciones p ON p.id = c.publicacion_id
WHERE p.estado = 'visible';
COMMENT ON VIEW api.comentarios IS 'Respuestas de publicaciones visibles; requiere JWT con rol nexo_lector.';

GRANT SELECT ON api.materias TO web_anon, nexo_lector;
GRANT SELECT ON api.publicaciones, api.comentarios TO nexo_lector;

-- PostgREST escucha este canal: recarga las vistas al confirmarse la transacción, sin reiniciarlo.
NOTIFY pgrst, 'reload schema';
