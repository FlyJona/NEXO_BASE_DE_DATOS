<?php
// Consultas y reglas de las publicaciones (PBI-03 y PBI-04).

const TIPOS_PUBLICACION = [
    'contenido' => 'Contenido',
    'pregunta' => 'Pregunta',
];

function obtener_publicacion(int $id): ?array
{
    $consulta = db()->prepare(
        'SELECT p.*, u.nombre AS autor_nombre, m.nombre AS materia_nombre,
                (SELECT COUNT(*) FROM votos v WHERE v.publicacion_id = p.id) AS total_votos,
                (SELECT COUNT(*) FROM comentarios c WHERE c.publicacion_id = p.id) AS total_comentarios
         FROM publicaciones p
         JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN materias m ON m.id = p.materia_id
         WHERE p.id = ?'
    );
    $consulta->execute([$id]);
    return $consulta->fetch() ?: null;
}

/** Las publicaciones ocultas por moderación solo las ven su autor y los moderadores. */
function puede_ver_publicacion(array $publicacion): bool
{
    return $publicacion['estado'] === 'visible' || es_autor($publicacion) || es_moderador();
}

function obtener_adjuntos(int $publicacionId): array
{
    $consulta = db()->prepare('SELECT * FROM adjuntos WHERE publicacion_id = ? ORDER BY id');
    $consulta->execute([$publicacionId]);
    return $consulta->fetchAll();
}

function obtener_comentarios(int $publicacionId): array
{
    $consulta = db()->prepare(
        'SELECT c.*, u.nombre AS autor_nombre
         FROM comentarios c JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.publicacion_id = ? ORDER BY c.creado_en, c.id'
    );
    $consulta->execute([$publicacionId]);
    return $consulta->fetchAll();
}

function tiene_relacion(string $tabla, int $usuarioId, int $publicacionId): bool
{
    // $tabla solo recibe valores fijos del código ('votos' o 'guardados').
    $consulta = db()->prepare("SELECT 1 FROM $tabla WHERE usuario_id = ? AND publicacion_id = ?");
    $consulta->execute([$usuarioId, $publicacionId]);
    return (bool) $consulta->fetchColumn();
}

/** Agrega o quita un voto / guardado. */
function alternar_relacion(string $tabla, int $usuarioId, int $publicacionId): void
{
    $borrar = db()->prepare("DELETE FROM $tabla WHERE usuario_id = ? AND publicacion_id = ?");
    $borrar->execute([$usuarioId, $publicacionId]);
    if ($borrar->rowCount() === 0) {
        db()->prepare("INSERT INTO $tabla (usuario_id, publicacion_id) VALUES (?, ?)")
            ->execute([$usuarioId, $publicacionId]);
    }
}

/**
 * Nombre del autor en HTML seguro, respetando la publicación resguardada (RC-08):
 * los demás ven "Estudiante resguardado"; el autor y los moderadores saben quién es.
 * $fila necesita usuario_id y autor_nombre.
 */
function nombre_autor(array $fila, bool $resguardado): string
{
    $enlace = '<a href="' . e(url('perfil.php?id=' . (int) $fila['usuario_id'])) . '">' . e($fila['autor_nombre']) . '</a>';
    if (!$resguardado) {
        return $enlace;
    }
    $etiqueta = '<span class="badge text-bg-secondary">Resguardado</span>';
    if (es_autor($fila)) {
        return 'Tú ' . $etiqueta;
    }
    if (es_moderador()) {
        return 'Estudiante resguardado ' . $etiqueta . ' <small class="text-muted">(visible para moderación: ' . $enlace . ')</small>';
    }
    return 'Estudiante resguardado';
}

/** Borra una publicación con sus archivos (comentarios, votos y adjuntos caen en cascada). */
function eliminar_publicacion(int $id): void
{
    $archivos = array_column(obtener_adjuntos($id), 'archivo');
    db()->prepare('DELETE FROM publicaciones WHERE id = ?')->execute([$id]);
    foreach ($archivos as $archivo) {
        eliminar_archivo_adjunto($archivo);
    }
}
