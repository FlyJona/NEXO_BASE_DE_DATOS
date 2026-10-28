<?php
// Acciones sobre una publicación (PBI-03 y PBI-04). Siempre por POST con token CSRF.
require __DIR__ . '/includes/inicio.php';

$usuario = requiere_login();
if (!es_post()) {
    redirigir('index.php');
}
verificar_csrf();

$accion = (string) ($_POST['accion'] ?? '');
$publicacion = obtener_publicacion((int) ($_POST['publicacion_id'] ?? 0));
if (!$publicacion || !puede_ver_publicacion($publicacion)) {
    no_encontrado();
}
$id = (int) $publicacion['id'];
$usuarioId = (int) $usuario['id'];
$volver = 'publicacion.php?id=' . $id;

switch ($accion) {
    case 'votar':
        requiere_permiso('votar');
        alternar_relacion('votos', $usuarioId, $id);
        break;

    case 'guardar':
        requiere_permiso('guardar');
        alternar_relacion('guardados', $usuarioId, $id);
        break;

    case 'comentar':
        requiere_permiso('comentar');
        $contenido = trim((string) ($_POST['contenido'] ?? ''));
        if (mb_strlen($contenido) < 2 || mb_strlen($contenido) > 5000) {
            flash('error', 'La respuesta debe tener de 2 a 5000 caracteres.');
        } else {
            db()->prepare('INSERT INTO comentarios (publicacion_id, usuario_id, contenido, resguardado) VALUES (?, ?, ?, ?)')
                ->execute([$id, $usuarioId, $contenido, isset($_POST['resguardado']) ? 1 : 0]);
            $volver .= '#comentario-' . db()->lastInsertId();
        }
        break;

    case 'eliminar_comentario':
        $consulta = db()->prepare('SELECT id, usuario_id FROM comentarios WHERE id = ? AND publicacion_id = ?');
        $consulta->execute([(int) ($_POST['comentario_id'] ?? 0), $id]);
        $comentario = $consulta->fetch();
        if (!$comentario || !(es_autor($comentario) || es_moderador())) {
            prohibido();
        }
        db()->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$comentario['id']]);
        flash('info', 'Respuesta eliminada.');
        $volver .= '#comentarios';
        break;

    case 'visibilidad':
        if (!es_autor($publicacion)) {
            prohibido();
        }
        $resguardada = $publicacion['resguardada'] ? 0 : 1;
        db()->prepare('UPDATE publicaciones SET resguardada = ? WHERE id = ?')->execute([$resguardada, $id]);
        flash('success', $resguardada ? 'Tu nombre ya no se muestra en esta publicación.' : 'Ahora tu nombre se muestra en esta publicación.');
        break;

    case 'moderar':
        requiere_permiso('moderar');
        $estado = $publicacion['estado'] === 'visible' ? 'oculta' : 'visible';
        db()->prepare('UPDATE publicaciones SET estado = ? WHERE id = ?')->execute([$estado, $id]);
        flash('success', $estado === 'oculta' ? 'Publicación ocultada.' : 'Publicación visible de nuevo.');
        break;

    case 'eliminar':
        if (!(es_autor($publicacion) || es_moderador())) {
            prohibido();
        }
        eliminar_publicacion($id);
        flash('info', 'Publicación eliminada.');
        redirigir('index.php');
        break;

    default:
        http_response_code(400);
        exit('Acción no válida.');
}

redirigir($volver);
