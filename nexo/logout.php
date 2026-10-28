<?php
// PBI-01: cierre de sesión (solo por POST con token CSRF).
require __DIR__ . '/includes/inicio.php';

if (es_post()) {
    verificar_csrf();
    cerrar_sesion();
    flash('info', 'Cerraste sesión.');
}
redirigir('login.php');
