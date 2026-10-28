<?php
// Sesión, usuario actual, roles y permisos (TR-01 / PBI-01).

const ROLES = [
    'estudiante' => 'Estudiante',
    'docente' => 'Docente',
    'moderador' => 'Moderador',
];

const PERMISOS = [
    'publicar' => ['estudiante', 'docente', 'moderador'],
    'comentar' => ['estudiante', 'docente', 'moderador'],
    'votar' => ['estudiante', 'docente', 'moderador'],
    'guardar' => ['estudiante', 'docente', 'moderador'],
    'moderar' => ['moderador'],
    'asignar_roles' => ['moderador'],
];

function iniciar_sesion_php(): void
{
    session_name('nexo_sesion');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function usuario_actual(): ?array
{
    static $usuario = false;
    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $consulta = db()->prepare('SELECT id, nombre, correo, rol, activo FROM usuarios WHERE id = ?');
            $consulta->execute([$_SESSION['usuario_id']]);
            $fila = $consulta->fetch();
            if ($fila && $fila['activo']) {
                $usuario = $fila;
            } else {
                unset($_SESSION['usuario_id']);
            }
        }
    }
    return $usuario;
}

function abrir_sesion(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function requiere_login(): array
{
    $usuario = usuario_actual();
    if (!$usuario) {
        flash('info', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }
    return $usuario;
}

function puede(string $permiso): bool
{
    $usuario = usuario_actual();
    return $usuario !== null && in_array($usuario['rol'], PERMISOS[$permiso] ?? [], true);
}

function requiere_permiso(string $permiso): array
{
    $usuario = requiere_login();
    if (!puede($permiso)) {
        prohibido();
    }
    return $usuario;
}

function es_moderador(): bool
{
    return puede('moderar');
}

/** true si el usuario actual es el autor de la fila (publicación, comentario…). */
function es_autor(array $fila): bool
{
    $usuario = usuario_actual();
    return $usuario !== null && (int) $usuario['id'] === (int) $fila['usuario_id'];
}
