<?php
// PBI-02: buscar estudiantes por materia, habilidad o interés.
require __DIR__ . '/includes/inicio.php';

requiere_login();
$materiaId = (int) ($_GET['materia'] ?? 0);
$texto = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT u.id, u.nombre, u.rol, p.carrera, p.semestre, p.habilidades, p.intereses
        FROM usuarios u LEFT JOIN perfiles p ON p.usuario_id = u.id
        WHERE u.activo = TRUE';
$parametros = [];
if ($materiaId) {
    $sql .= ' AND EXISTS (SELECT 1 FROM usuario_materias um WHERE um.usuario_id = u.id AND um.materia_id = ?)';
    $parametros[] = $materiaId;
}
if ($texto !== '') {
    $sql .= ' AND (' . sql_contiene('u.nombre') . ' OR ' . sql_contiene('p.habilidades') . ' OR ' . sql_contiene('p.intereses') . ')';
    array_push($parametros, patron_like($texto), patron_like($texto), patron_like($texto));
}
$consulta = db()->prepare($sql . ' ORDER BY u.nombre LIMIT 60');
$consulta->execute($parametros);
$estudiantes = $consulta->fetchAll();

$titulo = 'Estudiantes';
require __DIR__ . '/includes/encabezado.php';
?>
<h1 class="h3">Conoce estudiantes afines</h1>
<form class="row g-2 mb-4" method="get">
    <div class="col-md-5">
        <input class="form-control" name="q" value="<?= e($texto) ?>" placeholder="Nombre, habilidad o interés (ej. robótica)">
    </div>
    <div class="col-md-5">
        <select class="form-select" name="materia">
            <option value="0">Todas las materias</option>
            <?php foreach (materias_por_semestre() as $semestre => $materias): ?>
                <optgroup label="Semestre <?= (int) $semestre ?>">
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= (int) $materia['id'] ?>" <?= $materiaId === (int) $materia['id'] ? 'selected' : '' ?>><?= e($materia['nombre']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
    </div>
</form>

<?php if (!$estudiantes): ?>
    <p class="text-muted">No encontramos estudiantes con esos criterios.</p>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($estudiantes as $estudiante): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-1"><a href="<?= e(url('perfil.php?id=' . (int) $estudiante['id'])) ?>"><?= e($estudiante['nombre']) ?></a></h2>
                    <small class="text-muted">
                        <?= e(ROLES[$estudiante['rol']]) ?>
                        <?= $estudiante['carrera'] ? ' · ' . e($estudiante['carrera']) : '' ?>
                        <?= $estudiante['semestre'] ? ' · ' . (int) $estudiante['semestre'] . '.º sem.' : '' ?>
                    </small>
                    <div class="mt-2">
                        <?php foreach (array_merge(etiquetas($estudiante['habilidades']), etiquetas($estudiante['intereses'])) as $etiqueta): ?>
                            <span class="badge text-bg-light border"><?= e($etiqueta) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
