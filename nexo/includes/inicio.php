<?php
// Punto de arranque común: cada página lo incluye en su primera línea.
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/publicaciones.php';
require_once __DIR__ . '/adjuntos.php';

date_default_timezone_set('America/Mexico_City');

if (APP_ENTORNO === 'desarrollo') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    http_response_code(500);
    echo '<h1>Ocurrió un error</h1>';
    if (APP_ENTORNO === 'desarrollo') {
        echo '<pre>' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<p>Inténtalo de nuevo en unos minutos.</p>';
    }
});

iniciar_sesion_php();
