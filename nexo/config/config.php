<?php
// Configuración general de NEXO.
// Para otro entorno (InfinityFree), crea config/config.local.php con tus propios define();
// ese archivo no se sube a GitHub y tiene prioridad sobre los valores de aquí.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('APP_NOMBRE') || define('APP_NOMBRE', 'NEXO');
defined('APP_ENTORNO') || define('APP_ENTORNO', 'desarrollo'); // 'desarrollo' o 'produccion'
defined('APP_URL') || define('APP_URL', 'http://localhost/nexo'); // sin diagonal al final

// Base de datos (valores por defecto de XAMPP).
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NOMBRE') || define('DB_NOMBRE', 'nexo');
defined('DB_USUARIO') || define('DB_USUARIO', 'root');
defined('DB_CONTRASENA') || define('DB_CONTRASENA', '');

// Dominio del correo institucional. PENDIENTE de confirmar con Sistemas / TI;
// mientras tanto se usa este dominio de prueba (premisa del Form 4).
defined('DOMINIO_CORREO') || define('DOMINIO_CORREO', 'tesh.edu.mx');

// Correo remitente para la recuperación de contraseña (solo en producción).
defined('CORREO_REMITENTE') || define('CORREO_REMITENTE', 'no-responder@' . DOMINIO_CORREO);

// Adjuntos.
defined('MAX_TAMANO_ADJUNTO') || define('MAX_TAMANO_ADJUNTO', 5 * 1024 * 1024); // 5 MB
defined('MAX_ADJUNTOS') || define('MAX_ADJUNTOS', 5);
defined('DIR_ADJUNTOS') || define('DIR_ADJUNTOS', __DIR__ . '/../uploads');
