<?php
// PBI-02: consultar un perfil.
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_login();
$id = (int) ($_GET['id'] ?? $usuario['id']);

$consulta = db()->prepare(
    'SELECT u.id, u.nombre, u.rol, u.activo, u.creado_en, p.carrera, p.semestre, p.biografia, p.habilidades, p.intereses
     FROM usuarios u LEFT JOIN perfiles p ON p.usuario_id = u.id WHERE u.id = ?'
);
$consulta->execute([$id]);
$perfil = $consulta->fetch();
if (!$perfil || (!$perfil['activo'] && !es_moderador())) {
    no_encontrado();
}
$esPropio = $id === (int) $usuario['id'];

$consulta = db()->prepare(
    'SELECT m.nombre FROM usuario_materias um JOIN materias m ON m.id = um.materia_id
     WHERE um.usuario_id = ? ORDER BY m.semestre, m.nombre'
);
$consulta->execute([$id]);
$materias = $consulta->fetchAll(PDO::FETCH_COLUMN);

// Las publicaciones resguardadas no aparecen en el perfil público, para no revelar al autor.
$sql = 'SELECT p.id, p.titulo, p.tipo, p.resguardada, p.estado, p.creado_en, m.nombre AS materia_nombre
        FROM publicaciones p LEFT JOIN materias m ON m.id = p.materia_id WHERE p.usuario_id = ?';
if (!$esPropio) {
    $sql .= " AND p.resguardada = FALSE AND p.estado = 'visible'";
}
$consulta = db()->prepare($sql . ' ORDER BY p.creado_en DESC LIMIT 20');
$consulta->execute([$id]);
$publicaciones = $consulta->fetchAll();

$titulo = $perfil['nombre'];
require __DIR__ . '/includes/encabezado.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-1"><?= e($perfil['nombre']) ?></h1>
                <span class="badge text-bg-primary"><?= e(ROLES[$perfil['rol']]) ?></span>
                <?php if (!$perfil['activo']): ?><span class="badge text-bg-danger">Desactivado</span><?php endif; ?>
                <p class="text-muted small mt-2 mb-2">
                    <?= e($perfil['carrera'] ?: 'Carrera sin especificar') ?>
                    <?= $perfil['semestre'] ? ' · ' . (int) $perfil['semestre'] . '.º semestre' : '' ?>
                </p>
                <?php if ($perfil['biografia']): ?><p><?= nl2br(e($perfil['biografia'])) ?></p><?php endif; ?>

                <h2 class="h6 mt-3">Habilidades</h2>
                <?php foreach (etiquetas($perfil['habilidades']) as $habilidad): ?>
                    <a class="badge text-bg-light border text-decoration-none" href="<?= e(url('estudiantes.php?q=' . urlencode($habilidad))) ?>"><?= e($habilidad) ?></a>
                <?php endforeach; ?>
                <?php if (!$perfil['habilidades']): ?><p class="text-muted small">Sin registrar.</p><?php endif; ?>

                <h2 class="h6 mt-3">Intereses</h2>
                <?php foreach (etiquetas($perfil['intereses']) as $interes): ?>
                    <a class="badge text-bg-light border text-decoration-none" href="<?= e(url('estudiantes.php?q=' . urlencode($interes))) ?>"><?= e($interes) ?></a>
                <?php endforeach; ?>
                <?php if (!$perfil['intereses']): ?><p class="text-muted small">Sin registrar.</p><?php endif; ?>

                <h2 class="h6 mt-3">Materias</h2>
                <?php if ($materias): ?>
                    <ul class="small mb-0"><?php foreach ($materias as $materia): ?><li><?= e($materia) ?></li><?php endforeach; ?></ul>
                <?php else: ?>
                    <p class="text-muted small mb-0">Sin registrar.</p>
                <?php endif; ?>

                <?php if ($esPropio): ?>
                    <a class="btn btn-primary btn-sm mt-3" href="<?= e(url('perfil_editar.php')) ?>">Editar perfil</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <h2 class="h5"><?= $esPropio ? 'Mis publicaciones' : 'Publicaciones' ?></h2>
        <?php if (!$publicaciones): ?>
            <p class="text-muted">Todavía no hay publicaciones.</p>
        <?php endif; ?>
        <div class="list-group">
            <?php foreach ($publicaciones as $publicacion): ?>
                <a class="list-group-item list-group-item-action" href="<?= e(url('publicacion.php?id=' . (int) $publicacion['id'])) ?>">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold"><?= e($publicacion['titulo']) ?></span>
                        <small class="text-muted"><?= e(fecha($publicacion['creado_en'])) ?></small>
                    </div>
                    <small class="text-muted"><?= e(TIPOS_PUBLICACION[$publicacion['tipo']]) ?><?= $publicacion['materia_nombre'] ? ' · ' . e($publicacion['materia_nombre']) : '' ?></small>
                    <?php if ($publicacion['resguardada']): ?><span class="badge text-bg-secondary">Resguardada</span><?php endif; ?>
                    <?php if ($publicacion['estado'] === 'oculta'): ?><span class="badge text-bg-warning">Oculta por moderación</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
