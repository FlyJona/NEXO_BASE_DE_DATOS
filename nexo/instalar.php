<?php
// Crea usuarios y publicaciones de prueba (datos ficticios, Form 4: "en las pruebas se usan datos ficticios").
// Solo funciona en modo desarrollo y si todavía no hay usuarios. Bórralo antes de subir a producción.
require __DIR__ . '/includes/inicio.php';

// Contraseña de todas las cuentas de prueba.
const CONTRASENA_PRUEBA = 'NexoPrueba2026';

if (APP_ENTORNO !== 'desarrollo') {
    prohibido();
}

$hayUsuarios = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
$creados = [];

if (es_post() && !$hayUsuarios) {
    verificar_csrf();
    $bd = db();
    $bd->beginTransaction();

    $cuentas = [
        ['Moderador NEXO', 'moderador', 'moderador', null, null, 'moderación, comunidad', 'seguridad digital'],
        ['Docente de Prueba', 'docente', 'docente', null, null, 'bases de datos, programación', 'asesorías'],
        ['Ana Prueba López', 'ana.prueba', 'estudiante', 'Ingeniería en Sistemas Computacionales', 3, 'Java, estructuras de datos', 'videojuegos, robótica'],
        ['Luis Prueba Ramírez', 'luis.prueba', 'estudiante', 'Ingeniería en Sistemas Computacionales', 5, 'PHP, MySQL, diseño web', 'emprendimiento, música'],
    ];
    $ids = [];
    foreach ($cuentas as [$nombre, $usuarioCorreo, $rol, $carrera, $semestre, $habilidades, $intereses]) {
        $correo = $usuarioCorreo . '@' . DOMINIO_CORREO;
        $bd->prepare('INSERT INTO usuarios (nombre, correo, contrasena_hash, rol) VALUES (?, ?, ?, ?)')
            ->execute([$nombre, $correo, password_hash(CONTRASENA_PRUEBA, PASSWORD_DEFAULT), $rol]);
        $ids[$usuarioCorreo] = (int) $bd->lastInsertId();
        $bd->prepare('INSERT INTO perfiles (usuario_id, carrera, semestre, habilidades, intereses) VALUES (?, ?, ?, ?, ?)')
            ->execute([$ids[$usuarioCorreo], $carrera, $semestre, $habilidades, $intereses]);
        $creados[] = [$nombre, $correo, ROLES[$rol]];
    }

    $materia = fn (string $nombre) => (int) $bd->query('SELECT id FROM materias WHERE nombre = ' . $bd->quote($nombre))->fetchColumn() ?: null;
    $asignar = $bd->prepare('INSERT INTO usuario_materias (usuario_id, materia_id) VALUES (?, ?)');
    foreach (['Estructura de Datos', 'Programación Orientada a Objetos', 'Cálculo Vectorial'] as $nombreMateria) {
        $asignar->execute([$ids['ana.prueba'], $materia($nombreMateria)]);
    }
    foreach (['Taller de Bases de Datos', 'Sistemas Operativos', 'Estructura de Datos'] as $nombreMateria) {
        $asignar->execute([$ids['luis.prueba'], $materia($nombreMateria)]);
    }

    $publicar = $bd->prepare('INSERT INTO publicaciones (usuario_id, materia_id, tipo, titulo, contenido, resguardada) VALUES (?, ?, ?, ?, ?, ?)');
    $publicar->execute([
        $ids['ana.prueba'], $materia('Estructura de Datos'), 'pregunta',
        '¿Cómo recorro una lista enlazada en Java?',
        "Tengo esta clase Nodo y no sé cómo imprimir todos los elementos:\n\n```java\nclass Nodo {\n    int dato;\n    Nodo siguiente;\n}\n```\n\n¿Alguien me explica?",
        1,
    ]);
    $preguntaId = (int) $bd->lastInsertId();
    $publicar->execute([
        $ids['luis.prueba'], $materia('Taller de Bases de Datos'), 'contenido',
        'Resumen: tipos de JOIN en MySQL',
        "Comparto mi resumen para el examen:\n\n- INNER JOIN: solo filas que coinciden.\n- LEFT JOIN: todas las de la izquierda.\n\n```sql\nSELECT a.nombre, c.nombre\nFROM alumnos a\nLEFT JOIN carreras c ON c.id = a.carrera_id;\n```",
        0,
    ]);
    $bd->prepare('INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (?, ?, ?)')
        ->execute([$preguntaId, $ids['luis.prueba'], "Usa un ciclo while hasta que el nodo sea null:\n\n```java\nNodo actual = cabeza;\nwhile (actual != null) {\n    System.out.println(actual.dato);\n    actual = actual.siguiente;\n}\n```"]);

    $bd->commit();
    $hayUsuarios = true;
}

$titulo = 'Datos de prueba';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="card shadow-sm mx-auto" style="max-width: 640px">
    <div class="card-body p-4">
        <h1 class="h4">Datos de prueba de NEXO</h1>
        <?php if ($creados): ?>
            <div class="alert alert-success">Se crearon las cuentas de prueba. La contraseña de todas está en la constante <code>CONTRASENA_PRUEBA</code> de <code>instalar.php</code> (y en el README).</div>
            <table class="table table-sm">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th></tr></thead>
                <tbody>
                <?php foreach ($creados as [$nombre, $correo, $rol]): ?>
                    <tr><td><?= e($nombre) ?></td><td><?= e($correo) ?></td><td><?= e($rol) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
        <?php elseif ($hayUsuarios): ?>
            <div class="alert alert-info">Ya hay usuarios registrados; no se creó nada para no duplicar datos.</div>
            <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
        <?php else: ?>
            <p>Esto crea 4 cuentas ficticias (moderador, docente y dos estudiantes), dos publicaciones y una respuesta para probar NEXO.</p>
            <form method="post">
                <?= campo_csrf() ?>
                <button class="btn btn-primary" type="submit">Crear datos de prueba</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
