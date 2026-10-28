<?php
// Funciones de apoyo: escape de HTML, redirecciones, mensajes, CSRF y validaciones.

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return APP_URL . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

function es_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Valor de texto enviado por POST, sin espacios al inicio ni al final. */
function valor(string $clave): string
{
    return trim((string) ($_POST[$clave] ?? ''));
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verificar_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}

function no_encontrado(): void
{
    http_response_code(404);
    $titulo = 'No encontrado';
    require __DIR__ . '/encabezado.php';
    echo '<div class="alert alert-warning">La página o publicación que buscas no existe o no tienes acceso.</div>';
    echo '<a class="btn btn-primary" href="' . e(url('index.php')) . '">Volver al inicio</a>';
    require __DIR__ . '/pie.php';
    exit;
}

function prohibido(): void
{
    http_response_code(403);
    exit('No tienes permiso para realizar esta acción.');
}

function correo_institucional(string $correo): bool
{
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return str_ends_with(mb_strtolower($correo), '@' . mb_strtolower(DOMINIO_CORREO));
}

/** Devuelve un mensaje de error o null si la contraseña es válida. */
function validar_contrasena(string $contrasena, string $confirmacion): ?string
{
    if (mb_strlen($contrasena) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
        return 'La contraseña debe combinar letras y números.';
    }
    if ($contrasena !== $confirmacion) {
        return 'Las contraseñas no coinciden.';
    }
    return null;
}

/** Convierte "PHP, diseño ,  php" en "PHP, diseño" (sin repetidos, máximo 15). */
function normalizar_etiquetas(string $texto): string
{
    $etiquetas = [];
    foreach (explode(',', $texto) as $etiqueta) {
        $etiqueta = mb_substr(trim(preg_replace('/\s+/', ' ', $etiqueta)), 0, 40);
        if ($etiqueta !== '') {
            $etiquetas[mb_strtolower($etiqueta)] ??= $etiqueta;
        }
    }
    return implode(', ', array_slice(array_values($etiquetas), 0, 15));
}

function etiquetas(?string $texto): array
{
    return $texto ? array_map('trim', explode(',', $texto)) : [];
}

/** Escapa los comodines de LIKE. */
function patron_like(string $texto): string
{
    return '%' . addcslashes($texto, '%_\\') . '%';
}

function fecha(string $fecha): string
{
    return date('d/m/Y H:i', strtotime($fecha));
}

/**
 * Convierte el texto de una publicación a HTML seguro.
 * Los bloques ```lenguaje ... ``` se muestran como código; el resto como párrafos.
 */
function render_contenido(string $texto): string
{
    $partes = preg_split('/```([\w+#-]*)\R(.*?)```/s', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    for ($i = 0; $i < count($partes); $i += 3) {
        // Quita el salto de línea pegado al bloque de código para no dejar huecos dobles.
        $parrafo = $i > 0 ? preg_replace('/^\R/', '', $partes[$i]) : $partes[$i];
        if (isset($partes[$i + 2])) {
            $parrafo = preg_replace('/\R$/', '', $parrafo);
        }
        $html .= nl2br(e($parrafo));
        if (isset($partes[$i + 2])) {
            $lenguaje = $partes[$i + 1];
            $clase = $lenguaje !== '' ? ' class="language-' . e($lenguaje) . '"' : '';
            $html .= '<pre><code' . $clase . '>' . e(rtrim($partes[$i + 2])) . '</code></pre>';
        }
    }
    return $html;
}

/** Texto corto para listados, sin los bloques de código. */
function extracto(string $texto, int $largo = 220): string
{
    $sinCodigo = trim(preg_replace('/```.*?```/s', ' [código] ', $texto));
    return mb_strlen($sinCodigo) > $largo ? mb_substr($sinCodigo, 0, $largo) . '…' : $sinCodigo;
}

/** Materias agrupadas por semestre: [semestre => [materias…]]. */
function materias_por_semestre(): array
{
    $grupos = [];
    foreach (db()->query('SELECT id, nombre, carrera, semestre FROM materias ORDER BY semestre, nombre') as $materia) {
        $grupos[(int) $materia['semestre']][] = $materia;
    }
    return $grupos;
}

function existe_materia(int $id): bool
{
    $consulta = db()->prepare('SELECT 1 FROM materias WHERE id = ?');
    $consulta->execute([$id]);
    return (bool) $consulta->fetchColumn();
}
