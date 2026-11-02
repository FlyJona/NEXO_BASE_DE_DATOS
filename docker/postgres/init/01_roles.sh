#!/bin/bash
# Se ejecuta UNA sola vez, cuando el contenedor arranca con un volumen vacío (docker-entrypoint-initdb.d).
# Crea los roles de NEXO con las contraseñas de .env y deja la base en manos de nexo_migrator.
# Las tablas NO se crean aquí: las crean las migraciones (scripts/apply_migrations.ps1).
# Sin "set -e": el entrypoint de la imagen ya lo usa y psql se detiene con ON_ERROR_STOP.

for variable in NEXO_MIGRATOR_PASSWORD NEXO_APP_PASSWORD PGRST_AUTHENTICATOR_PASSWORD; do
    if [ -z "${!variable:-}" ]; then
        echo "01_roles.sh: falta la variable $variable (revisa .env)" >&2
        exit 1
    fi
done

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v dbname="$POSTGRES_DB" \
    -v migrator_pw="$NEXO_MIGRATOR_PASSWORD" \
    -v app_pw="$NEXO_APP_PASSWORD" \
    -v auth_pw="$PGRST_AUTHENTICATOR_PASSWORD" <<'EOSQL'
CREATE ROLE nexo_migrator LOGIN PASSWORD :'migrator_pw';
CREATE ROLE nexo_app LOGIN PASSWORD :'app_pw';
CREATE ROLE authenticator LOGIN NOINHERIT PASSWORD :'auth_pw';
CREATE ROLE web_anon NOLOGIN;
CREATE ROLE nexo_lector NOLOGIN;
GRANT web_anon, nexo_lector TO authenticator;

ALTER DATABASE :"dbname" OWNER TO nexo_migrator;
ALTER ROLE nexo_app SET search_path = nexo, public;
ALTER ROLE nexo_app SET timezone = 'America/Mexico_City';
EOSQL
