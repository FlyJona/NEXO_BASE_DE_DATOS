<?php
// PBI-03: crear y editar publicaciones con imágenes, archivos y bloques de código.
// PBI-04: opción de publicación resguardada.
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_permiso('publicar');
$id = (int) ($_GET['id'] ?? 0);
$publicacion = null;
$adjuntos = [];

if ($id) {
    $publicacion = obtener_publicacion($id);
    if (!$publicacion || !es_autor($publicacion)) {
        no_encontrado();
    }
    $adjuntos = obtener_adjuntos($id);
}

$datos = [
    'tipo' => $publicacion['tipo'] ?? (($_GET['tipo'] ?? '') === 'pregunta' ? 'pregunta' : 'contenido'),
    'materia_id' => (int) ($publicacion['materia_id'] ?? 0),
    'titulo' => $publicacion['titulo'] ?? '',
    'contenido' => $publicacion['contenido'] ?? '',
    'resguardada' => (int) ($publicacion['resguardada'] ?? 0),
];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $datos = [
        'tipo' => array_key_exists((string) ($_POST['tipo'] ?? ''), TIPOS_PUBLICACION) ? (string) $_POST['tipo'] : 'contenido',
        'materia_id' => (int) ($_POST['materia_id'] ?? 0),
        'titulo' => valor('titulo'),
        'contenido' => trim((string) ($_POST['contenido'] ?? '')),
        'resguardada' => isset($_POST['resguardada']) ? 1 : 0,
    ];
    $archivos = normalizar_archivos($_FILES['adjuntos'] ?? null);
    $idsAdjuntos = array_map('intval', array_column($adjuntos, 'id'));
    $eliminar = array_values(array_intersect(array_map('intval', (array) ($_POST['eliminar_adjuntos'] ?? [])), $idsAdjuntos));

    if (mb_strlen($datos['titulo']) < 5 || mb_strlen($datos['titulo']) > 150) {
        $errores[] = 'El título debe tener de 5 a 150 caracteres.';
    }
    if (mb_strlen($datos['contenido']) < 10) {
        $errores[] = 'Describe tu publicación con al menos 10 caracteres.';
    }
    if (mb_strlen($datos['contenido']) > 20000) {
        $errores[] = 'El contenido es demasiado largo (máximo 20 000 caracteres).';
    }
    if ($datos['materia_id'] && !existe_materia($datos['materia_id'])) {
        $errores[] = 'La materia seleccionada no existe.';
    }
    if ($datos['tipo'] === 'pregunta' && !$datos['materia_id']) {
        $errores[] = 'Elige la materia de tu pregunta para que te respondan más rápido.';
    }
    if (count($adjuntos) - count($eliminar) + count($archivos) > MAX_ADJUNTOS) {
        $errores[] = 'Puedes adjuntar máximo ' . MAX_ADJUNTOS . ' archivos por publicación.';
    }
    foreach ($archivos as $archivo) {
        if ($error = validar_adjunto($archivo)) {
            $errores[] = $error;
        }
    }

    if (!$errores) {
        $bd = db();
        $guardados = [];
        $bd->beginTransaction();
        try {
            $valores = [$datos['materia_id'] ?: null, $datos['tipo'], $datos['titulo'], $datos['contenido'], $datos['resguardada']];
            if ($publicacion) {
                $bd->prepare('UPDATE publicaciones SET materia_id = ?, tipo = ?, titulo = ?, contenido = ?, resguardada = ? WHERE id = ?')
                    ->execute([...$valores, $id]);
            } else {
                $bd->prepare('INSERT INTO publicaciones (materia_id, tipo, titulo, contenido, resguardada, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([...$valores, $usuario['id']]);
                $id = (int) $bd->lastInsertId();
            }

            $archivosBorrados = [];
            foreach ($adjuntos as $adjunto) {
                if (in_array((int) $adjunto['id'], $eliminar, true)) {
                    $bd->prepare('DELETE FROM adjuntos WHERE id = ?')->execute([$adjunto['id']]);
                    $archivosBorrados[] = $adjunto['archivo'];
                }
            }
            foreach ($archivos as $archivo) {
                $guardados[] = guardar_adjunto($id, $archivo);
            }
            $bd->commit();
        } catch (Throwable $error) {
            $bd->rollBack();
            foreach ($guardados as $guardado) {
                eliminar_archivo_adjunto($guardado);
            }
            throw $error;
        }
        foreach ($archivosBorrados as $archivo) {
            eliminar_archivo_adjunto($archivo);
        }

        flash('success', $publicacion ? 'Publicación actualizada.' : '¡Listo! Tu publicación ya está en NEXO.');
        redirigir('publicacion.php?id=' . $id);
    }
}

$titulo = $publicacion ? 'Editar publicación' : 'Nueva publicación';
require __DIR__ . '/includes/encabezado.php';
?>
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>
<?php require __DIR__ . '/includes/errores.php'; ?>
<form method="post" enctype="multipart/form-data" class="card shadow-sm">
    <div class="card-body p-4">
        <?= campo_csrf() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="tipo">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <?php foreach (TIPOS_PUBLICACION as $clave => $nombreTipo): ?>
                        <option value="<?= e($clave) ?>" <?= $datos['tipo'] === $clave ? 'selected' : '' ?>><?= e($nombreTipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="materia_id">Materia</label>
                <select class="form-select" id="materia_id" name="materia_id">
                    <option value="0">Sin materia (tema general)</option>
                    <?php foreach (materias_por_semestre() as $semestre => $materias): ?>
                        <optgroup label="Semestre <?= (int) $semestre ?>">
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?= (int) $materia['id'] ?>" <?= $datos['materia_id'] === (int) $materia['id'] ? 'selected' : '' ?>><?= e($materia['nombre']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="titulo">Título</label>
                <input class="form-control" id="titulo" name="titulo" maxlength="150" required value="<?= e($datos['titulo']) ?>"
                       placeholder="Ej. ¿Cómo recorro una lista enlazada en Java?">
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-1">
                    <label class="form-label mb-0" for="contenido">Contenido</label>
                    <div class="input-group input-group-sm w-auto">
                        <input class="form-control" id="lenguaje-codigo" placeholder="lenguaje (java, php…)" style="max-width: 11rem">
                        <button class="btn btn-outline-secondary" type="button" data-insertar-codigo="contenido">
                            <i class="bi bi-code-slash"></i> Insertar bloque de código
                        </button>
                    </div>
                </div>
                <textarea class="form-control font-monospace" id="contenido" name="contenido" rows="12" required><?= e($datos['contenido']) ?></textarea>
                <div class="form-text">Para código escribe ```lenguaje en una línea, tu código y ``` al final (o usa el botón).</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="adjuntos">Imágenes y archivos</label>
                <input class="form-control" id="adjuntos" name="adjuntos[]" type="file" multiple
                       accept=".<?= e(implode(',.', array_keys(TIPOS_ADJUNTO))) ?>">
                <div class="form-text">
                    Máximo <?= MAX_ADJUNTOS ?> archivos de <?= MAX_TAMANO_ADJUNTO / 1024 / 1024 ?> MB:
                    <?= e(implode(', ', array_keys(TIPOS_ADJUNTO))) ?>.
                </div>
                <?php if ($adjuntos): ?>
                    <div class="mt-2">
                        <div class="small fw-semibold">Adjuntos actuales (marca los que quieras quitar):</div>
                        <?php foreach ($adjuntos as $adjunto): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="eliminar_adjuntos[]" value="<?= (int) $adjunto['id'] ?>" id="quitar-<?= (int) $adjunto['id'] ?>">
                                <label class="form-check-label small" for="quitar-<?= (int) $adjunto['id'] ?>">
                                    <?= e($adjunto['nombre_original']) ?> (<?= e(tamano_legible((int) $adjunto['tamano'])) ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <div class="form-check form-switch p-3 ps-5 border rounded bg-light">
                    <input class="form-check-input" type="checkbox" role="switch" id="resguardada" name="resguardada" <?= $datos['resguardada'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="resguardada">
                        <strong>Publicar de forma resguardada</strong><br>
                        <small class="text-muted">Tus compañeros verán "Estudiante resguardado" en lugar de tu nombre.
                            Solo los moderadores pueden saber quién publicó, para mantener la comunidad segura. Puedes cambiarlo después.</small>
                    </label>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" type="submit"><?= $publicacion ? 'Guardar cambios' : 'Publicar' ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url($publicacion ? 'publicacion.php?id=' . $id : 'index.php')) ?>">Cancelar</a>
        </div>
    </div>
</form>
<?php require __DIR__ . '/includes/pie.php'; ?>
