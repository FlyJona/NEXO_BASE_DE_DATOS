-- Verifica restricciones, relaciones y triggers con datos ficticios. Todo se revierte al final.
BEGIN;
SET LOCAL search_path TO nexo, public;

DO $$
DECLARE
    u1 bigint;
    u2 bigint;
    p1 bigint;
    m1 bigint;
    n integer;
    antes timestamptz;
    despues timestamptz;
BEGIN
    INSERT INTO usuarios (nombre, correo, contrasena_hash) VALUES ('Prueba Uno', 'uno@prueba.test', 'x') RETURNING id INTO u1;
    INSERT INTO usuarios (nombre, correo, contrasena_hash, rol) VALUES ('Prueba Dos', 'dos@prueba.test', 'x', 'docente') RETURNING id INTO u2;

    -- Valores por defecto (rol estudiante, activo)
    PERFORM 1 FROM usuarios WHERE id = u1 AND rol = 'estudiante' AND activo;
    IF NOT FOUND THEN RAISE EXCEPTION 'Valores por defecto de usuarios incorrectos'; END IF;

    -- Correo repetido
    BEGIN
        INSERT INTO usuarios (nombre, correo, contrasena_hash) VALUES ('Otra', 'uno@prueba.test', 'x');
        RAISE EXCEPTION 'Aceptó un correo repetido';
    EXCEPTION WHEN unique_violation THEN NULL;
    END;
    -- Correo con mayúsculas (la unicidad de MySQL no distinguía mayúsculas)
    BEGIN
        INSERT INTO usuarios (nombre, correo, contrasena_hash) VALUES ('Otra', 'UNO@prueba.test', 'x');
        RAISE EXCEPTION 'Aceptó un correo con mayúsculas';
    EXCEPTION WHEN check_violation THEN NULL;
    END;
    -- Rol fuera del ENUM original
    BEGIN
        INSERT INTO usuarios (nombre, correo, contrasena_hash, rol) VALUES ('Otra', 'tres@prueba.test', 'x', 'admin');
        RAISE EXCEPTION 'Aceptó un rol inválido';
    EXCEPTION WHEN check_violation THEN NULL;
    END;
    -- Semestre fuera de rango
    BEGIN
        INSERT INTO perfiles (usuario_id, semestre) VALUES (u1, 13);
        RAISE EXCEPTION 'Aceptó semestre 13';
    EXCEPTION WHEN check_violation THEN NULL;
    END;
    -- Materia repetida sin distinguir mayúsculas
    BEGIN
        INSERT INTO materias (nombre, carrera, semestre) VALUES ('CÁLCULO DIFERENCIAL', 'ingeniería en sistemas computacionales', 1);
        RAISE EXCEPTION 'Aceptó una materia repetida';
    EXCEPTION WHEN unique_violation THEN NULL;
    END;
    -- Tipo y estado de publicación
    BEGIN
        INSERT INTO publicaciones (usuario_id, titulo, contenido, tipo) VALUES (u1, 'Título', 'Contenido', 'encuesta');
        RAISE EXCEPTION 'Aceptó un tipo inválido';
    EXCEPTION WHEN check_violation THEN NULL;
    END;
    -- Llave foránea inexistente
    BEGIN
        INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (-1, u1, 'x');
        RAISE EXCEPTION 'Aceptó un comentario huérfano';
    EXCEPTION WHEN foreign_key_violation THEN NULL;
    END;

    -- Trigger actualizado_en: no cambia si nada cambió; cambia si algo cambió
    -- (now() es fijo dentro de la transacción: se apaga el trigger un momento para poner una fecha antigua)
    INSERT INTO perfiles (usuario_id, carrera) VALUES (u1, 'ISC');
    ALTER TABLE perfiles DISABLE TRIGGER perfiles_actualizado_en;
    UPDATE perfiles SET actualizado_en = '2000-01-01' WHERE usuario_id = u1;
    ALTER TABLE perfiles ENABLE TRIGGER perfiles_actualizado_en;
    SELECT actualizado_en INTO antes FROM perfiles WHERE usuario_id = u1;
    UPDATE perfiles SET carrera = 'ISC' WHERE usuario_id = u1;
    SELECT actualizado_en INTO despues FROM perfiles WHERE usuario_id = u1;
    IF despues <> antes THEN RAISE EXCEPTION 'El trigger cambió la fecha sin cambios en la fila'; END IF;
    UPDATE perfiles SET carrera = 'Sistemas' WHERE usuario_id = u1;
    SELECT actualizado_en INTO despues FROM perfiles WHERE usuario_id = u1;
    IF despues <= antes THEN RAISE EXCEPTION 'El trigger no actualizó actualizado_en'; END IF;

    -- ON DELETE SET NULL: borrar una materia deja la publicación sin materia
    INSERT INTO materias (nombre, carrera, semestre) VALUES ('Materia temporal', 'Prueba', 1) RETURNING id INTO m1;
    INSERT INTO publicaciones (usuario_id, materia_id, titulo, contenido) VALUES (u1, m1, 'Título', 'Contenido') RETURNING id INTO p1;
    INSERT INTO usuario_materias (usuario_id, materia_id) VALUES (u1, m1);
    DELETE FROM materias WHERE id = m1;
    PERFORM 1 FROM publicaciones WHERE id = p1 AND materia_id IS NULL;
    IF NOT FOUND THEN RAISE EXCEPTION 'ON DELETE SET NULL de publicaciones.materia_id no funcionó'; END IF;

    -- ON DELETE CASCADE: borrar el usuario borra todo lo suyo
    INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (p1, u2, 'Respuesta');
    INSERT INTO votos (usuario_id, publicacion_id) VALUES (u2, p1);
    INSERT INTO guardados (usuario_id, publicacion_id) VALUES (u2, p1);
    INSERT INTO adjuntos (publicacion_id, nombre_original, archivo, tipo_mime, tamano) VALUES (p1, 'a.pdf', 'x.pdf', 'application/pdf', 10);
    BEGIN
        INSERT INTO votos (usuario_id, publicacion_id) VALUES (u2, p1);
        RAISE EXCEPTION 'Aceptó un voto repetido';
    EXCEPTION WHEN unique_violation THEN NULL;
    END;
    DELETE FROM usuarios WHERE id = u1;
    SELECT (SELECT count(*) FROM publicaciones WHERE id = p1) + (SELECT count(*) FROM comentarios WHERE publicacion_id = p1)
         + (SELECT count(*) FROM votos WHERE publicacion_id = p1) + (SELECT count(*) FROM adjuntos WHERE publicacion_id = p1)
         + (SELECT count(*) FROM perfiles WHERE usuario_id = u1) INTO n;
    IF n <> 0 THEN RAISE EXCEPTION 'ON DELETE CASCADE dejó % filas', n; END IF;

    RAISE NOTICE 'restricciones: OK';
END;
$$;

ROLLBACK;
