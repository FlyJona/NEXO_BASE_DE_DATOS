<?php
// Imágenes y archivos adjuntos de las publicaciones (RC-11 / PBI-03).

/** Extensiones permitidas y los tipos MIME aceptados para cada una. */
const TIPOS_ADJUNTO = [
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'pdf' => ['application/pdf'],
    'txt' => ['text/plain'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
];

/** Convierte $_FILES['campo'] (subida múltiple) en una lista de archivos, sin los vacíos. */
function normalizar_archivos(?array $campo): array
{
    if (!$campo || !is_array($campo['name'] ?? null)) {
        return [];
    }
    $archivos = [];
    foreach ($campo['name'] as $i => $nombre) {
        if ($campo['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $archivos[] = [
            'name' => (string) $nombre,
            'tmp_name' => $campo['tmp_name'][$i],
            'error' => $campo['error'][$i],
            'size' => (int) $campo['size'][$i],
        ];
    }
    return $archivos;
}

function detectar_mime(string $ruta): string
{
    if (class_exists('finfo')) {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    }
    return function_exists('mime_content_type') ? (string) mime_content_type($ruta) : 'application/octet-stream';
}

/** Devuelve un mensaje de error o null si el archivo es válido. */
function validar_adjunto(array $archivo): ?string
{
    $nombre = $archivo['name'];
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE || $archivo['size'] > MAX_TAMANO_ADJUNTO) {
        return "\"$nombre\" supera el tamaño máximo de " . (MAX_TAMANO_ADJUNTO / 1024 / 1024) . ' MB.';
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return "No se pudo subir \"$nombre\".";
    }
    $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!array_key_exists($extension, TIPOS_ADJUNTO)) {
        return "\"$nombre\" no es un tipo de archivo permitido.";
    }
    if (!in_array(detectar_mime($archivo['tmp_name']), TIPOS_ADJUNTO[$extension], true)) {
        return "El contenido de \"$nombre\" no corresponde a su extensión.";
    }
    return null;
}

/** Mueve el archivo a uploads/ con un nombre aleatorio y lo registra. Devuelve el nombre guardado. */
function guardar_adjunto(int $publicacionId, array $archivo): string
{
    if (!is_dir(DIR_ADJUNTOS) && !mkdir(DIR_ADJUNTOS, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de adjuntos.');
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $guardado = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], DIR_ADJUNTOS . '/' . $guardado)) {
        throw new RuntimeException('No se pudo guardar el archivo ' . $archivo['name']);
    }
    $mime = detectar_mime(DIR_ADJUNTOS . '/' . $guardado);
    db()->prepare(
        'INSERT INTO adjuntos (publicacion_id, nombre_original, archivo, tipo_mime, tamano, es_imagen)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $publicacionId,
        mb_substr(basename($archivo['name']), 0, 255),
        $guardado,
        $mime,
        $archivo['size'],
        str_starts_with($mime, 'image/') ? 1 : 0,
    ]);
    return $guardado;
}

function eliminar_archivo_adjunto(string $archivo): void
{
    $ruta = DIR_ADJUNTOS . '/' . basename($archivo);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}

function tamano_legible(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
}
