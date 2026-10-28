<?php
// PBI-01: registro solo con correo institucional.
require __DIR__ . '/includes/inicio.php';

if (usuario_actual()) {
    redirigir('index.php');
}

$errores = [];
$nombre = '';
$correo = '';

if (es_post()) {
    verificar_csrf();
    $nombre = valor('nombre');
    $correo = mb_strtolower(valor('correo'));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $confirmacion = (string) ($_POST['confirmacion'] ?? '');

    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
        $errores[] = 'Escribe tu nombre completo (de 3 a 100 caracteres).';
    }
    if (!correo_institucional($correo)) {
        $errores[] = 'Usa tu correo institucional (@' . DOMINIO_CORREO . ').';
    }
    if ($error = validar_contrasena($contrasena, $confirmacion)) {
        $errores[] = $error;
    }
    if (!isset($_POST['acepto'])) {
        $errores[] = 'Debes aceptar las reglas de la comunidad y el aviso de privacidad.';
    }

    if (!$errores) {
        $existe = db()->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
        $existe->execute([$correo]);
        if ($existe->fetchColumn()) {
            $errores[] = 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.';
        }
    }

    if (!$errores) {
        $bd = db();
        $bd->beginTransaction();
        $bd->prepare('INSERT INTO usuarios (nombre, correo, contrasena_hash) VALUES (?, ?, ?)')
            ->execute([$nombre, $correo, password_hash($contrasena, PASSWORD_DEFAULT)]);
        $usuarioId = (int) $bd->lastInsertId();
        $bd->prepare('INSERT INTO perfiles (usuario_id) VALUES (?)')->execute([$usuarioId]);
        $bd->commit();

        abrir_sesion($usuarioId);
        flash('success', '¡Bienvenido a NEXO! Ya puedes publicar tu primera duda o completar tu perfil.');
        redirigir('index.php');
    }
}

$titulo = 'Crear cuenta';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="caja-acceso mx-auto">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">Crear cuenta</h1>
            <p class="text-muted small">Exclusivo para la comunidad del TESH.</p>
            <?php require __DIR__ . '/includes/errores.php'; ?>
            <form method="post" novalidate>
                <?= campo_csrf() ?>
                <div class="mb-3">
                    <label class="form-label" for="nombre">Nombre completo</label>
                    <input class="form-control" id="nombre" name="nombre" maxlength="100" required value="<?= e($nombre) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="correo">Correo institucional</label>
                    <input class="form-control" id="correo" name="correo" type="email" required
                           placeholder="matricula@<?= e(DOMINIO_CORREO) ?>" value="<?= e($correo) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="contrasena">Contraseña</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" minlength="8" required>
                    <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirmacion">Confirmar contraseña</label>
                    <input class="form-control" id="confirmacion" name="confirmacion" type="password" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="acepto" name="acepto" <?= isset($_POST['acepto']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="acepto">
                        Acepto las reglas de la comunidad y el uso de mis datos solo para operar NEXO.
                    </label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Crear cuenta</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3 small">¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión</a></p>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
