<?php
// PBI-01: asignar roles y activar / desactivar cuentas (solo moderadores).
require __DIR__ . '/includes/inicio.php';

$moderador = requiere_permiso('asignar_roles');

if (es_post()) {
    verificar_csrf();
    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    $rol = (string) ($_POST['rol'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($usuarioId === (int) $moderador['id']) {
        flash('error', 'No puedes cambiar tu propio rol ni desactivar tu cuenta.');
    } elseif (!array_key_exists($rol, ROLES)) {
        flash('error', 'Rol no válido.');
    } else {
        db()->prepare('UPDATE usuarios SET rol = ?, activo = ? WHERE id = ?')->execute([$rol, $activo, $usuarioId]);
        flash('success', 'Usuario actualizado.');
    }
    redirigir('usuarios.php');
}

$usuarios = db()->query('SELECT id, nombre, correo, rol, activo, creado_en FROM usuarios ORDER BY creado_en DESC')->fetchAll();

$titulo = 'Usuarios';
require __DIR__ . '/includes/encabezado.php';
?>
<h1 class="h3">Usuarios y roles</h1>
<p class="text-muted">Todos se registran como estudiantes. Asigna aquí el rol de docente o moderador.</p>
<div class="table-responsive">
    <table class="table table-sm align-middle bg-white">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Registro</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $fila): ?>
            <?php $propio = (int) $fila['id'] === (int) $moderador['id']; ?>
            <tr>
                <td><a href="<?= e(url('perfil.php?id=' . (int) $fila['id'])) ?>"><?= e($fila['nombre']) ?></a></td>
                <td class="small"><?= e($fila['correo']) ?></td>
                <td class="small"><?= e(fecha($fila['creado_en'])) ?></td>
                <td colspan="3">
                    <form method="post" class="d-flex gap-2 align-items-center">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="usuario_id" value="<?= (int) $fila['id'] ?>">
                        <select class="form-select form-select-sm w-auto" name="rol" <?= $propio ? 'disabled' : '' ?>>
                            <?php foreach (ROLES as $clave => $nombreRol): ?>
                                <option value="<?= e($clave) ?>" <?= $fila['rol'] === $clave ? 'selected' : '' ?>><?= e($nombreRol) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="form-check-input" type="checkbox" name="activo" aria-label="Activo" <?= $fila['activo'] ? 'checked' : '' ?> <?= $propio ? 'disabled' : '' ?>>
                        <?php if (!$propio): ?>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button>
                        <?php else: ?>
                            <small class="text-muted">(tú)</small>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
