<?php
// PBI-02: crear y editar el perfil (materias cursadas, habilidades e intereses).
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_login();
$usuarioId = (int) $usuario['id'];

$consulta = db()->prepare('SELECT * FROM perfiles WHERE usuario_id = ?');
$consulta->execute([$usuarioId]);
$perfil = $consulta->fetch() ?: [];

$consulta = db()->prepare('SELECT materia_id FROM usuario_materias WHERE usuario_id = ?');
$consulta->execute([$usuarioId]);
$materiasSeleccionadas = array_map('intval', array_column($consulta->fetchAll(), 'materia_id'));

$datos = [
    'nombre' => $usuario['nombre'],
    'carrera' => $perfil['carrera'] ?? '',
    'semestre' => $perfil['semestre'] ?? '',
    'biografia' => $perfil['biografia'] ?? '',
    'habilidades' => $perfil['habilidades'] ?? '',
    'intereses' => $perfil['intereses'] ?? '',
];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $datos = [
        'nombre' => valor('nombre'),
        'carrera' => valor('carrera'),
        'semestre' => valor('semestre'),
        'biografia' => valor('biografia'),
        'habilidades' => normalizar_etiquetas(valor('habilidades')),
        'intereses' => normalizar_etiquetas(valor('intereses')),
    ];
    $idsValidos = array_map('intval', db()->query('SELECT id FROM materias')->fetchAll(PDO::FETCH_COLUMN));
    $materiasSeleccionadas = array_values(array_intersect(array_map('intval', (array) ($_POST['materias'] ?? [])), $idsValidos));

    if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 100) {
        $errores[] = 'Escribe tu nombre completo (de 3 a 100 caracteres).';
    }
    if (mb_strlen($datos['carrera']) > 100) {
        $errores[] = 'El nombre de la carrera es demasiado largo.';
    }
    if ($datos['semestre'] !== '' && (!ctype_digit($datos['semestre']) || (int) $datos['semestre'] < 1 || (int) $datos['semestre'] > 12)) {
        $errores[] = 'El semestre debe ser un número del 1 al 12.';
    }
    if (mb_strlen($datos['biografia']) > 500) {
        $errores[] = 'La descripción admite máximo 500 caracteres.';
    }

    if (!$errores) {
        $bd = db();
        $bd->beginTransaction();
        $bd->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')->execute([$datos['nombre'], $usuarioId]);
        $bd->prepare(
            'INSERT INTO perfiles (usuario_id, carrera, semestre, biografia, habilidades, intereses)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE carrera = VALUES(carrera), semestre = VALUES(semestre),
                 biografia = VALUES(biografia), habilidades = VALUES(habilidades), intereses = VALUES(intereses)'
        )->execute([
            $usuarioId,
            $datos['carrera'] ?: null,
            $datos['semestre'] !== '' ? (int) $datos['semestre'] : null,
            $datos['biografia'] ?: null,
            $datos['habilidades'] ?: null,
            $datos['intereses'] ?: null,
        ]);
        $bd->prepare('DELETE FROM usuario_materias WHERE usuario_id = ?')->execute([$usuarioId]);
        $insertar = $bd->prepare('INSERT INTO usuario_materias (usuario_id, materia_id) VALUES (?, ?)');
        foreach ($materiasSeleccionadas as $materiaId) {
            $insertar->execute([$usuarioId, $materiaId]);
        }
        $bd->commit();
        flash('success', 'Tu perfil se guardó.');
        redirigir('perfil.php');
    }
}

$titulo = 'Editar perfil';
require __DIR__ . '/includes/encabezado.php';
?>
<h1 class="h3 mb-3">Mi perfil</h1>
<?php require __DIR__ . '/includes/errores.php'; ?>
<form method="post" class="card shadow-sm">
    <div class="card-body p-4">
        <?= campo_csrf() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nombre">Nombre completo</label>
                <input class="form-control" id="nombre" name="nombre" maxlength="100" required value="<?= e($datos['nombre']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="carrera">Carrera</label>
                <input class="form-control" id="carrera" name="carrera" maxlength="100" value="<?= e($datos['carrera']) ?>"
                       placeholder="Ingeniería en Sistemas Computacionales">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="semestre">Semestre</label>
                <input class="form-control" id="semestre" name="semestre" type="number" min="1" max="12" value="<?= e((string) $datos['semestre']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="biografia">Sobre mí</label>
                <textarea class="form-control" id="biografia" name="biografia" rows="2" maxlength="500"><?= e($datos['biografia']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="habilidades">Habilidades</label>
                <input class="form-control" id="habilidades" name="habilidades" value="<?= e($datos['habilidades']) ?>"
                       placeholder="PHP, diseño, cálculo">
                <div class="form-text">Sepáralas con comas.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="intereses">Intereses</label>
                <input class="form-control" id="intereses" name="intereses" value="<?= e($datos['intereses']) ?>"
                       placeholder="videojuegos, robótica, emprendimiento">
                <div class="form-text">Sepáralos con comas.</div>
            </div>
        </div>

        <h2 class="h5 mt-4">Materias que cursas o cursaste</h2>
        <div class="row">
            <?php foreach (materias_por_semestre() as $semestre => $materias): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="fw-semibold small text-muted mb-1">Semestre <?= (int) $semestre ?></div>
                    <?php foreach ($materias as $materia): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="materias[]" value="<?= (int) $materia['id'] ?>"
                                   id="materia-<?= (int) $materia['id'] ?>" <?= in_array((int) $materia['id'], $materiasSeleccionadas, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="materia-<?= (int) $materia['id'] ?>"><?= e($materia['nombre']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Guardar perfil</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('perfil.php')) ?>">Cancelar</a>
        </div>
    </div>
</form>
<?php require __DIR__ . '/includes/pie.php'; ?>
