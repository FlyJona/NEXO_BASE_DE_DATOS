<?php
// Inicio: publicaciones recientes de la comunidad, con filtro por materia y tipo.
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_login();
$materiaId = (int) ($_GET['materia'] ?? 0);
$tipo = (string) ($_GET['tipo'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;

$condiciones = [];
$parametros = [];
if (!es_moderador()) {
    $condiciones[] = "(p.estado = 'visible' OR p.usuario_id = ?)";
    $parametros[] = $usuario['id'];
}
if ($materiaId) {
    $condiciones[] = 'p.materia_id = ?';
    $parametros[] = $materiaId;
}
if (array_key_exists($tipo, TIPOS_PUBLICACION)) {
    $condiciones[] = 'p.tipo = ?';
    $parametros[] = $tipo;
}

$sql = 'SELECT p.id, p.usuario_id, p.materia_id, p.tipo, p.titulo, p.contenido, p.resguardada, p.estado, p.creado_en,
               u.nombre AS autor_nombre, m.nombre AS materia_nombre,
               (SELECT COUNT(*) FROM votos v WHERE v.publicacion_id = p.id) AS total_votos,
               (SELECT COUNT(*) FROM comentarios c WHERE c.publicacion_id = p.id) AS total_comentarios,
               (SELECT COUNT(*) FROM adjuntos a WHERE a.publicacion_id = p.id) AS total_adjuntos
        FROM publicaciones p
        JOIN usuarios u ON u.id = p.usuario_id
        LEFT JOIN materias m ON m.id = p.materia_id'
    . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
    . ' ORDER BY p.creado_en DESC, p.id DESC'
    . ' LIMIT ' . ($porPagina + 1) . ' OFFSET ' . (($pagina - 1) * $porPagina);
$consulta = db()->prepare($sql);
$consulta->execute($parametros);
$publicaciones = $consulta->fetchAll();
$hayMas = count($publicaciones) > $porPagina;
$publicaciones = array_slice($publicaciones, 0, $porPagina);

$consulta = db()->prepare(
    'SELECT (p.carrera IS NOT NULL OR p.habilidades IS NOT NULL OR p.intereses IS NOT NULL) FROM perfiles p WHERE p.usuario_id = ?'
);
$consulta->execute([$usuario['id']]);
$perfilCompleto = (bool) $consulta->fetchColumn();

/** Enlace a esta misma página cambiando solo algunos filtros. */
function enlace_filtro(array $cambios): string
{
    $parametros = array_filter(array_merge($_GET, $cambios), fn ($valor) => $valor !== '' && $valor !== 0 && $valor !== null);
    return url('index.php' . ($parametros ? '?' . http_build_query($parametros) : ''));
}

$titulo = 'Inicio';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0">Comunidad</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="<?= e(url('publicar.php?tipo=pregunta')) ?>"><i class="bi bi-question-circle"></i> Preguntar</a>
                <a class="btn btn-primary" href="<?= e(url('publicar.php')) ?>"><i class="bi bi-plus-lg"></i> Publicar</a>
            </div>
        </div>

        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-7">
                <select class="form-select" name="materia" onchange="this.form.submit()" aria-label="Filtrar por materia">
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
            <div class="col-sm-5">
                <select class="form-select" name="tipo" onchange="this.form.submit()" aria-label="Filtrar por tipo">
                    <option value="">Preguntas y contenidos</option>
                    <?php foreach (TIPOS_PUBLICACION as $clave => $nombreTipo): ?>
                        <option value="<?= e($clave) ?>" <?= $tipo === $clave ? 'selected' : '' ?>>Solo <?= e(mb_strtolower($nombreTipo)) ?>s</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (!$publicaciones): ?>
            <div class="card card-body text-center text-muted">
                Todavía no hay publicaciones aquí. ¡Sé la primera persona en preguntar o compartir algo!
            </div>
        <?php endif; ?>

        <?php foreach ($publicaciones as $publicacion): ?>
            <div class="card mb-3 tarjeta-publicacion">
                <div class="card-body">
                    <div class="mb-1">
                        <span class="badge <?= $publicacion['tipo'] === 'pregunta' ? 'text-bg-info' : 'text-bg-primary' ?>"><?= e(TIPOS_PUBLICACION[$publicacion['tipo']]) ?></span>
                        <?php if ($publicacion['materia_nombre']): ?>
                            <a class="badge text-bg-light border text-decoration-none" href="<?= e(enlace_filtro(['materia' => (int) $publicacion['materia_id'], 'pagina' => null])) ?>"><?= e($publicacion['materia_nombre']) ?></a>
                        <?php endif; ?>
                        <?php if ($publicacion['estado'] === 'oculta'): ?><span class="badge text-bg-warning">Oculta</span><?php endif; ?>
                    </div>
                    <h2 class="h5 mb-1">
                        <a class="text-decoration-none stretched-link" href="<?= e(url('publicacion.php?id=' . (int) $publicacion['id'])) ?>"><?= e($publicacion['titulo']) ?></a>
                    </h2>
                    <p class="mb-2 text-body-secondary"><?= e(extracto($publicacion['contenido'])) ?></p>
                    <div class="d-flex flex-wrap justify-content-between small text-muted">
                        <span class="position-relative" style="z-index: 2"><?= nombre_autor($publicacion, (bool) $publicacion['resguardada']) ?> · <?= e(fecha($publicacion['creado_en'])) ?></span>
                        <span>
                            <i class="bi bi-hand-thumbs-up"></i> <?= (int) $publicacion['total_votos'] ?>
                            <i class="bi bi-chat ms-2"></i> <?= (int) $publicacion['total_comentarios'] ?>
                            <?php if ($publicacion['total_adjuntos']): ?><i class="bi bi-paperclip ms-2"></i> <?= (int) $publicacion['total_adjuntos'] ?><?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <nav class="d-flex justify-content-between">
            <?php if ($pagina > 1): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(enlace_filtro(['pagina' => $pagina - 1])) ?>">← Más recientes</a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($hayMas): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(enlace_filtro(['pagina' => $pagina + 1])) ?>">Anteriores →</a>
            <?php endif; ?>
        </nav>
    </div>

    <aside class="col-lg-4">
        <?php if (!$perfilCompleto): ?>
            <div class="card border-primary mb-3">
                <div class="card-body">
                    <h2 class="h6">Completa tu perfil</h2>
                    <p class="small text-muted mb-2">Agrega tus materias, habilidades e intereses para encontrar compañeros afines.</p>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('perfil_editar.php')) ?>">Completar perfil</a>
                </div>
            </div>
        <?php endif; ?>
        <div class="card shadow-sm niveles-participacion">
            <div class="card-body">
                <h2 class="h6">Participa a tu ritmo</h2>
                <div class="nivel small mb-2"><strong>1. Lee</strong> las dudas de tus compañeros.</div>
                <div class="nivel small mb-2"><strong>2. Vota o guarda</strong> lo que te sirvió.</div>
                <div class="nivel small mb-2"><strong>3. Responde</strong>, si quieres sin mostrar tu nombre.</div>
                <div class="nivel small"><strong>4. Pregunta</strong> de forma normal o resguardada.</div>
            </div>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
