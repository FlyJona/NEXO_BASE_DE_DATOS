-- Verifica que el esquema creado por las migraciones corresponde al MySQL de origen.
-- Se ejecuta como superusuario en la base de prueba. Solo lee: no deja cambios.
DO $$
DECLARE
    faltantes text;
    n integer;
BEGIN
    -- 10 tablas de nexo/database/nexo.sql
    SELECT string_agg(t, ', ') INTO faltantes
    FROM unnest(ARRAY['usuarios', 'recuperaciones', 'perfiles', 'materias', 'usuario_materias', 'publicaciones',
                      'adjuntos', 'comentarios', 'votos', 'guardados']) AS t
    WHERE to_regclass('nexo.' || t) IS NULL;
    IF faltantes IS NOT NULL THEN RAISE EXCEPTION 'Faltan tablas: %', faltantes; END IF;

    SELECT count(*) INTO n FROM information_schema.tables WHERE table_schema = 'nexo' AND table_type = 'BASE TABLE';
    IF n <> 10 THEN RAISE EXCEPTION 'Se esperaban 10 tablas en nexo, hay %', n; END IF;

    -- 13 llaves foráneas, igual que el MySQL de origen
    SELECT count(*) INTO n FROM pg_constraint c JOIN pg_namespace s ON s.oid = c.connamespace
    WHERE s.nspname = 'nexo' AND c.contype = 'f';
    IF n <> 13 THEN RAISE EXCEPTION 'Se esperaban 13 llaves foráneas, hay %', n; END IF;

    -- Toda llave foránea tiene un índice que empieza por su columna (InnoDB los crea solo)
    SELECT string_agg(c.conrelid::regclass || '.' || a.attname, ', ') INTO faltantes
    FROM pg_constraint c
    JOIN pg_namespace s ON s.oid = c.connamespace
    JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
    WHERE s.nspname = 'nexo' AND c.contype = 'f'
      AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = c.conrelid AND i.indkey[0] = c.conkey[1]);
    IF faltantes IS NOT NULL THEN RAISE EXCEPTION 'Llaves foráneas sin índice: %', faltantes; END IF;

    -- Identidades en lugar de AUTO_INCREMENT
    SELECT count(*) INTO n FROM information_schema.columns
    WHERE table_schema = 'nexo' AND column_name = 'id' AND is_identity = 'YES';
    IF n <> 6 THEN RAISE EXCEPTION 'Se esperaban 6 columnas id de identidad, hay %', n; END IF;

    -- Columnas booleanas (antes TINYINT(1))
    SELECT count(*) INTO n FROM information_schema.columns
    WHERE table_schema = 'nexo' AND data_type = 'boolean';
    IF n <> 5 THEN RAISE EXCEPTION 'Se esperaban 5 columnas boolean, hay %', n; END IF;

    -- Índice de texto completo (antes FULLTEXT)
    IF to_regclass('nexo.publicaciones_busqueda_idx') IS NULL THEN
        RAISE EXCEPTION 'Falta el índice GIN de búsqueda';
    END IF;

    -- Triggers de actualizado_en
    SELECT count(*) INTO n FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid
    WHERE NOT t.tgisinternal AND c.relname IN ('perfiles', 'publicaciones');
    IF n <> 2 THEN RAISE EXCEPTION 'Se esperaban 2 triggers, hay %', n; END IF;

    -- Catálogo de materias
    SELECT count(*) INTO n FROM nexo.materias;
    IF n <> 38 THEN RAISE EXCEPTION 'Se esperaban 38 materias, hay %', n; END IF;

    -- Vistas de la API
    SELECT count(*) INTO n FROM information_schema.views WHERE table_schema = 'api';
    IF n <> 3 THEN RAISE EXCEPTION 'Se esperaban 3 vistas en api, hay %', n; END IF;

    -- Historial
    SELECT count(*) INTO n FROM nexo_meta.schema_migrations;
    IF n <> 5 THEN RAISE EXCEPTION 'Se esperaban 5 migraciones registradas, hay %', n; END IF;

    RAISE NOTICE 'esquema: OK';
END;
$$;
