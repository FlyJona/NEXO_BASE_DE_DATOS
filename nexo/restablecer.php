<?php
// PBI-01: crear una nueva contraseña con el enlace de recuperación.
require __DIR__ . '/includes/inicio.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$consulta = db()->prepare(
    'SELECT id, usuario_id FROM recuperaciones WHERE token_hash = ? AND usado = FALSE AND expira_en > ?'
);
$consulta->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
$recuperacion = $consulta->fetch();
$errores = [];

if ($recuperacion && es_post()) {
    verificar_csrf();
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    if ($error = validar_contrasena($contrasena, (string) ($_POST['confirmacion'] ?? ''))) {
        $errores[] = $error;
    } else {
        $bd = db();
        $bd->beginTransaction();
        $bd->prepare('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?')
            ->execute([password_hash($contrasena, PASSWORD_DEFAULT), $recuperacion['usuario_id']]);
        // Invalida este y cualquier otro enlace pendiente del usuario.
        $bd->prepare('UPDATE recuperaciones SET usado = TRUE WHERE usuario_id = ?')->execute([$recuperacion['usuario_id']]);
        $bd->commit();
        flash('success', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
        redirigir('login.php');
    }
}

$titulo = 'Nueva contraseña';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="caja-acceso mx-auto">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4">Nueva contraseña</h1>
            <?php if (!$recuperacion): ?>
                <div class="alert alert-warning">El enlace no es válido o ya venció.</div>
                <a class="btn btn-primary" href="<?= e(url('recuperar.php')) ?>">Solicitar otro enlace</a>
            <?php else: ?>
                <?php require __DIR__ . '/includes/errores.php'; ?>
                <form method="post" novalidate>
                    <?= campo_csrf() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="contrasena">Nueva contraseña</label>
                        <input class="form-control" id="contrasena" name="contrasena" type="password" minlength="8" required>
                        <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmacion">Confirmar contraseña</label>
                        <input class="form-control" id="confirmacion" name="confirmacion" type="password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Guardar contraseña</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
