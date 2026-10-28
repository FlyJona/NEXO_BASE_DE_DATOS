<?php
// Encabezado común. Antes de incluirlo, cada página define $titulo.
$usuarioMenu = usuario_actual();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? '') !== '' ? $titulo . ' · ' . APP_NOMBRE : APP_NOMBRE) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/nexo.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-nexo">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>"><i class="bi bi-diagram-3"></i> NEXO</a>
        <?php if ($usuarioMenu): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('publicar.php')) ?>">Publicar</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('estudiantes.php')) ?>">Estudiantes</a></li>
                    <?php if (puede('asignar_roles')): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('usuarios.php')) ?>">Usuarios</a></li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('perfil.php')) ?>"><i class="bi bi-person-circle"></i> <?= e($usuarioMenu['nombre']) ?>
                            <span class="badge text-bg-light"><?= e(ROLES[$usuarioMenu['rol']]) ?></span></a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                            <?= campo_csrf() ?>
                            <button class="btn btn-link nav-link" type="submit">Salir</button>
                        </form>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container py-4">
    <?php foreach (obtener_flash() as $mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo'] === 'error' ? 'danger' : $mensaje['tipo']) ?> alert-dismissible fade show">
            <?= e($mensaje['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
