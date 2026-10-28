<?php
// PBI-01: solicitar la recuperación de contraseña.
require __DIR__ . '/includes/inicio.php';

$enviado = false;
$enlaceDesarrollo = null;
$errores = [];

if (es_post()) {
    verificar_csrf();
    $correo = mb_strtolower(valor('correo'));

    if (!correo_institucional($correo)) {
        $errores[] = 'Escribe tu correo institucional (@' . DOMINIO_CORREO . ').';
    } else {
        $consulta = db()->prepare('SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1');
        $consulta->execute([$correo]);
        $usuario = $consulta->fetch();

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO recuperaciones (usuario_id, token_hash, expira_en) VALUES (?, ?, ?)')
                ->execute([$usuario['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
            $enlace = url('restablecer.php?token=' . $token);

            if (APP_ENTORNO === 'desarrollo') {
                // XAMPP no envía correos: el enlace se muestra en pantalla solo en desarrollo.
                $enlaceDesarrollo = $enlace;
            } else {
                $mensaje = "Hola {$usuario['nombre']}:\n\nPara crear una nueva contraseña de NEXO entra a:\n$enlace\n\n"
                    . "El enlace vence en 1 hora. Si no lo solicitaste, ignora este correo.";
                mail($correo, 'Recupera tu contraseña de NEXO', $mensaje, 'From: ' . CORREO_REMITENTE);
            }
        }
        // El mismo mensaje exista o no la cuenta, para no revelar qué correos están registrados.
        $enviado = true;
    }
}

$titulo = 'Recuperar contraseña';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="caja-acceso mx-auto">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4">Recuperar contraseña</h1>
            <?php if ($enviado): ?>
                <div class="alert alert-success">Si el correo está registrado, te enviamos un enlace para crear una nueva contraseña. Vence en 1 hora.</div>
                <?php if ($enlaceDesarrollo): ?>
                    <div class="alert alert-warning small">
                        <strong>Modo desarrollo:</strong> el correo no se envía. Usa este enlace:<br>
                        <a href="<?= e($enlaceDesarrollo) ?>"><?= e($enlaceDesarrollo) ?></a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted small">Escribe tu correo institucional y te enviaremos un enlace.</p>
                <?php require __DIR__ . '/includes/errores.php'; ?>
                <form method="post" novalidate>
                    <?= campo_csrf() ?>
                    <div class="mb-3">
                        <label class="form-label" for="correo">Correo institucional</label>
                        <input class="form-control" id="correo" name="correo" type="email" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Enviar enlace</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-center mt-3 small"><a href="<?= e(url('login.php')) ?>">Volver a iniciar sesión</a></p>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
