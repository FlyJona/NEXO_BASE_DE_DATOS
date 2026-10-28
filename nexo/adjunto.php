<?php
// Entrega imágenes y archivos solo a usuarios con sesión que pueden ver la publicación.
require __DIR__ . '/includes/inicio.php';

requiere_login();
$consulta = db()->prepare(
    'SELECT a.*, p.estado, p.usuario_id FROM adjuntos a JOIN publicaciones p ON p.id = a.publicacion_id WHERE a.id = ?'
);
$consulta->execute([(int) ($_GET['id'] ?? 0)]);
$adjunto = $consulta->fetch();
if (!$adjunto || !puede_ver_publicacion($adjunto)) {
    no_encontrado();
}

$ruta = DIR_ADJUNTOS . '/' . basename($adjunto['archivo']);
if (!is_file($ruta)) {
    no_encontrado();
}

$enLinea = $adjunto['es_imagen'] && isset($_GET['ver']);
$nombre = str_replace(['"', "\r", "\n"], '', $adjunto['nombre_original']);
$nombreAscii = preg_replace('/[^\x20-\x7E]/', '_', $nombre);

header('Content-Type: ' . $adjunto['tipo_mime']);
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombreAscii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('Cache-Control: private, max-age=3600');
readfile($ruta);
exit;
