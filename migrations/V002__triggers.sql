-- V002: triggers.
-- El esquema MySQL no tiene triggers, vistas ni procedimientos. Lo único que se comporta como trigger
-- es "actualizado_en ... ON UPDATE CURRENT_TIMESTAMP" en perfiles y publicaciones, que PostgreSQL no tiene.
-- Este trigger reproduce esa regla: MySQL solo cambia la fecha si alguna columna cambió de valor.

SET LOCAL search_path TO nexo, public;

CREATE OR REPLACE FUNCTION nexo.fijar_actualizado_en()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW IS DISTINCT FROM OLD THEN
        NEW.actualizado_en := now();
    END IF;
    RETURN NEW;
END;
$$;

COMMENT ON FUNCTION nexo.fijar_actualizado_en() IS
    'Equivalente de ON UPDATE CURRENT_TIMESTAMP de MySQL para la columna actualizado_en.';

DROP TRIGGER IF EXISTS perfiles_actualizado_en ON perfiles;
CREATE TRIGGER perfiles_actualizado_en
    BEFORE UPDATE ON perfiles
    FOR EACH ROW EXECUTE FUNCTION nexo.fijar_actualizado_en();

DROP TRIGGER IF EXISTS publicaciones_actualizado_en ON publicaciones;
CREATE TRIGGER publicaciones_actualizado_en
    BEFORE UPDATE ON publicaciones
    FOR EACH ROW EXECUTE FUNCTION nexo.fijar_actualizado_en();
