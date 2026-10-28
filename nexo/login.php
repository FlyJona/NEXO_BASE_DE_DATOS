<?php
// PBI-01: inicio de sesión.
require __DIR__ . '/includes/inicio.php';

if (usuario_actual()) {
    redirigir('index.php');
}

$errores = [];
$correo = '';

if (es_post()) {
    verificar_csrf();
    $correo = mb_strtolower(valor('correo'));
    $contrasena = (string) ($_POST['contrasena'] ?? '');

    $consulta = db()->prepare('SELECT id, contrasena_hash, activo FROM usuarios WHERE correo = ?');
    $consulta->execute([$correo]);
    $usuario = $consulta->fetch();

    if (!$usuario || !password_verify($contrasena, $usuario['contrasena_hash'])) {
        $errores[] = 'Correo o contraseña incorrectos.';
    } elseif (!$usuario['activo']) {
        $errores[] = 'Tu cuenta está desactivada. Contacta a un moderador.';
    } else {
        if (password_needs_rehash($usuario['contrasena_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?')
                ->execute([password_hash($contrasena, PASSWORD_DEFAULT), $usuario['id']]);
        }
        abrir_sesion((int) $usuario['id']);
        redirigir('index.php');
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="row justify-content-center align-items-center g-4">
    <div class="col-lg-5">
        <h1 class="display-6 fw-bold">NEXO</h1>
        <p class="lead">La comunidad estudiantil digital del TESH.</p>
        <ul class="list-unstyled text-muted">
            <li><i class="bi bi-question-circle"></i> Publica tus dudas por materia y responde a tus compañeros.</li>
            <li><i class="bi bi-search"></i> Encuentra rápido lo que ya se preguntó.</li>
            <li><i class="bi bi-people"></i> Conoce estudiantes con tus mismos intereses.</li>
            <li><i class="bi bi-shield-lock"></i> Participa sin mostrar tu nombre si lo prefieres.</li>
        </ul>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm caja-acceso mx-auto">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Iniciar sesión</h2>
                <?php require __DIR__ . '/includes/errores.php'; ?>
                <form method="post" novalidate>
                    <?= campo_csrf() ?>
                    <div class="mb-3">
                        <label class="form-label" for="correo">Correo institucional</label>
                        <input class="form-control" id="correo" name="correo" type="email" required autofocus value="<?= e($correo) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contrasena">Contraseña</label>
                        <input class="form-control" id="contrasena" name="contrasena" type="password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Entrar</button>
                </form>
                <div class="d-flex justify-content-between mt-3 small">
                    <a href="<?= e(url('recuperar.php')) ?>">¿Olvidaste tu contraseña?</a>
                    <a href="<?= e(url('registro.php')) ?>">Crear cuenta</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
