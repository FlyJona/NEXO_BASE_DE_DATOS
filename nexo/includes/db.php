<?php
// Conexión única con PDO a PostgreSQL o MySQL (según DB_MOTOR).

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (DB_MOTOR === 'pgsql') {
            $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PUERTO . ';dbname=' . DB_NOMBRE;
            $pdo = new PDO($dsn, DB_USUARIO, DB_CONTRASENA, $opciones);
            // Las tablas viven en el esquema "nexo"; unaccent está en "public".
            $pdo->exec("SET search_path TO nexo, public; SET TIME ZONE 'America/Mexico_City'");
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PUERTO . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USUARIO, DB_CONTRASENA, $opciones);
        }
    }
    return $pdo;
}

/**
 * Condición "la columna contiene el texto" sin distinguir mayúsculas ni acentos,
 * como hace la intercalación utf8mb4_unicode_ci de MySQL. El valor se pasa con patron_like().
 */
function sql_contiene(string $columna): string
{
    return DB_MOTOR === 'pgsql' ? "unaccent($columna) ILIKE unaccent(?)" : "$columna LIKE ?";
}
