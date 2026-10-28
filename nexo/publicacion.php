<?php
// PBI-03: ver una publicación con sus adjuntos.
// PBI-04: participación escalonada (votar, guardar, comentar, resguardar).
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_login();
$publicacion = obtener_publicacion((int) ($_GET['id'] ?? 0));
if (!$publicacion || !puede_ver_publicacion($publicacion)) {
    no_encontrado();
}
$id = (int) $publicacion['id'];
$adjuntos = obtener_adjuntos($id);
$comentarios = obtener_comentarios($id);
$votado = tiene_relacion('votos', (int) $usuario['id'], $id);
$guardado = tiene_relacion('guardados', (int) $usuario['id'], $id);
$imagenes = array_filter($adjuntos, fn ($adjunto) => $adjunto['es_imagen']);
$archivos = array_filter($adjuntos, fn ($adjunto) => !$adjunto['es_imagen']);

/** Formulario POST de una sola acción hacia acciones.php. */
function boton_accion(int $publicacionId, string $accion, string $contenido, string $clase, string $confirmar = '', array $extra = []): string
{
    $html = '<form method="post" action="' . e(url('acciones.php')) . '" class="d-inline"'
        . ($confirmar !== '' ? ' data-confirmar="' . e($confirmar) . '"' : '') . '>'
        . campo_csrf()
        . '<input type="hidden" name="accion" value="' . e($accion) . '">'
        . '<input type="hidden" name="publicacion_id" value="' . $publicacionId . '">';
    foreach ($extra as $nombre => $valor) {
        $html .= '<input type="hidden" name="' . e($nombre) . '" value="' . e((string) $valor) . '">';
    }
    return $html . '<button class="btn btn-sm ' . e($clase) . '" type="submit">' . $contenido . '</button></form>';
}

$titulo = $publicacion['titulo'];
require __DIR__ . '/includes/encabezado.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <article class="card shadow-sm">
            <div class="card-body p-4">
                <?php if ($publicacion['estado'] === 'oculta'): ?>
                    <div class="alert alert-warning py-2 small">Esta publicación está oculta por moderación; solo la ven su autor y los moderadores.</div>
                <?php endif; ?>
                <div class="mb-2">
                    <span class="badge <?= $publicacion['tipo'] === 'pregunta' ? 'text-bg-info' : 'text-bg-primary' ?>"><?= e(TIPOS_PUBLICACION[$publicacion['tipo']]) ?></span>
                    <?php if ($publicacion['materia_nombre']): ?>
                        <a class="badge text-bg-light border text-decoration-none" href="<?= e(url('index.php?materia=' . (int) $publicacion['materia_id'])) ?>"><?= e($publicacion['materia_nombre']) ?></a>
                    <?php endif; ?>
                </div>
                <h1 class="h3"><?= e($publicacion['titulo']) ?></h1>
                <p class="text-muted small">
                    <?= nombre_autor($publicacion, (bool) $publicacion['resguardada']) ?> · <?= e(fecha($publicacion['creado_en'])) ?>
                </p>
                <div class="contenido-publicacion"><?= render_contenido($publicacion['contenido']) ?></div>

                <?php foreach ($imagenes as $imagen): ?>
                    <a href="<?= e(url('adjunto.php?id=' . (int) $imagen['id'] . '&ver=1')) ?>" target="_blank" rel="noopener">
                        <img class="img-fluid rounded border mt-3 adjunto-imagen d-block" alt="<?= e($imagen['nombre_original']) ?>"
                             src="<?= e(url('adjunto.php?id=' . (int) $imagen['id'] . '&ver=1')) ?>">
                    </a>
                <?php endforeach; ?>

                <?php if ($archivos): ?>
                    <div class="list-group mt-3">
                        <?php foreach ($archivos as $archivo): ?>
                            <a class="list-group-item list-group-item-action small" href="<?= e(url('adjunto.php?id=' . (int) $archivo['id'])) ?>">
                                <i class="bi bi-paperclip"></i> <?= e($archivo['nombre_original']) ?>
                                <span class="text-muted">(<?= e(tamano_legible((int) $archivo['tamano'])) ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <?php if (puede('votar')): ?>
                        <?= boton_accion($id, 'votar', '<i class="bi bi-hand-thumbs-up' . ($votado ? '-fill' : '') . '"></i> Útil (' . (int) $publicacion['total_votos'] . ')', $votado ? 'btn-primary' : 'btn-outline-primary') ?>
                    <?php endif; ?>
                    <?php if (puede('guardar')): ?>
                        <?= boton_accion($id, 'guardar', '<i class="bi bi-bookmark' . ($guardado ? '-fill' : '') . '"></i> ' . ($guardado ? 'Guardada' : 'Guardar'), $guardado ? 'btn-secondary' : 'btn-outline-secondary') ?>
                    <?php endif; ?>

                    <?php if (es_autor($publicacion)): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('publicar.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Editar</a>
                        <?= boton_accion($id, 'visibilidad', $publicacion['resguardada'] ? '<i class="bi bi-eye"></i> Mostrar mi nombre' : '<i class="bi bi-eye-slash"></i> Resguardar mi nombre', 'btn-outline-secondary') ?>
                    <?php endif; ?>
                    <?php if (es_moderador()): ?>
                        <?= boton_accion($id, 'moderar', $publicacion['estado'] === 'visible' ? '<i class="bi bi-shield-exclamation"></i> Ocultar' : '<i class="bi bi-shield-check"></i> Volver a mostrar', 'btn-outline-warning') ?>
                    <?php endif; ?>
                    <?php if (es_autor($publicacion) || es_moderador()): ?>
                        <?= boton_accion($id, 'eliminar', '<i class="bi bi-trash"></i> Eliminar', 'btn-outline-danger', '¿Eliminar esta publicación? No se puede deshacer.') ?>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <section class="mt-4" id="comentarios">
            <h2 class="h5"><?= count($comentarios) ?> <?= count($comentarios) === 1 ? 'respuesta' : 'respuestas' ?></h2>
            <?php foreach ($comentarios as $comentario): ?>
                <div class="card mb-2" id="comentario-<?= (int) $comentario['id'] ?>">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between small text-muted">
                            <span><?= nombre_autor($comentario, (bool) $comentario['resguardado']) ?> · <?= e(fecha($comentario['creado_en'])) ?></span>
                            <?php if (es_autor($comentario) || es_moderador()): ?>
                                <?= boton_accion($id, 'eliminar_comentario', '<i class="bi bi-trash"></i>', 'btn-link text-danger p-0', '¿Eliminar esta respuesta?', ['comentario_id' => (int) $comentario['id']]) ?>
                            <?php endif; ?>
                        </div>
                        <div class="contenido-publicacion mt-1"><?= render_contenido($comentario['contenido']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (puede('comentar')): ?>
                <form method="post" action="<?= e(url('acciones.php')) ?>" class="card mt-3">
                    <div class="card-body">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="comentar">
                        <input type="hidden" name="publicacion_id" value="<?= $id ?>">
                        <label class="form-label" for="comentario">Tu respuesta</label>
                        <textarea class="form-control" id="comentario" name="contenido" rows="4" maxlength="5000" required
                                  placeholder="Comparte lo que sabes. Para código usa ```lenguaje ... ```"></textarea>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="resguardado" name="resguardado">
                                <label class="form-check-label small" for="resguardado">Responder de forma resguardada (sin mostrar mi nombre)</label>
                            </div>
                            <button class="btn btn-primary btn-sm" type="submit">Responder</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <aside class="col-lg-4">
        <div class="card shadow-sm niveles-participacion">
            <div class="card-body">
                <h2 class="h6">Participa a tu ritmo</h2>
                <p class="small text-muted">No necesitas exponerte para aportar. Empieza por el nivel que te acomode:</p>
                <div class="nivel small mb-2"><strong>1. Leer</strong> y aprender de las dudas de otros.</div>
                <div class="nivel small mb-2"><strong>2. Votar o guardar</strong> lo que te sirvió.</div>
                <div class="nivel small mb-2"><strong>3. Responder</strong>, si quieres de forma resguardada.</div>
                <div class="nivel small"><strong>4. Preguntar o publicar</strong> tu propio contenido.</div>
            </div>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
