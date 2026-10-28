-- NEXO: Comunidad estudiantil digital
-- Esquema de base de datos (Sprint 1 y Sprint 2).
-- Importar en phpMyAdmin (pestaña "Importar").
-- En InfinityFree la base ya existe con otro nombre: borra las dos líneas siguientes antes de importar.
CREATE DATABASE IF NOT EXISTS nexo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexo;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- PBI-01 Cuentas, acceso y roles
-- ---------------------------------------------------------------
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol ENUM('estudiante', 'docente', 'moderador') NOT NULL DEFAULT 'estudiante',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recuperaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_en DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- PBI-02 Perfiles y taxonomía de materias
-- ---------------------------------------------------------------
CREATE TABLE perfiles (
    usuario_id INT UNSIGNED PRIMARY KEY,
    carrera VARCHAR(100) NULL,
    semestre TINYINT UNSIGNED NULL,
    biografia VARCHAR(500) NULL,
    habilidades VARCHAR(500) NULL,
    intereses VARCHAR(500) NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    carrera VARCHAR(100) NOT NULL,
    semestre TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY materia_unica (nombre, carrera)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_materias (
    usuario_id INT UNSIGNED NOT NULL,
    materia_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, materia_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- PBI-03 Publicaciones y adjuntos
-- ---------------------------------------------------------------
CREATE TABLE publicaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    materia_id INT UNSIGNED NULL,
    tipo ENUM('contenido', 'pregunta') NOT NULL DEFAULT 'contenido',
    titulo VARCHAR(150) NOT NULL,
    contenido TEXT NOT NULL,
    resguardada TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('visible', 'oculta') NOT NULL DEFAULT 'visible',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE SET NULL,
    INDEX idx_publicaciones_fecha (creado_en),
    FULLTEXT INDEX ft_publicaciones (titulo, contenido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE adjuntos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publicacion_id INT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    archivo VARCHAR(60) NOT NULL,
    tipo_mime VARCHAR(120) NOT NULL,
    tamano INT UNSIGNED NOT NULL,
    es_imagen TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- PBI-04 Participación escalonada (guardar, votar, comentar)
-- ---------------------------------------------------------------
CREATE TABLE comentarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publicacion_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    contenido TEXT NOT NULL,
    resguardado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE votos (
    usuario_id INT UNSIGNED NOT NULL,
    publicacion_id INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, publicacion_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guardados (
    usuario_id INT UNSIGNED NOT NULL,
    publicacion_id INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, publicacion_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Taxonomía de materias (paquete 1.3.3)
-- EJEMPLO basado en un plan genérico de Ingeniería en Sistemas Computacionales.
-- Debe validarse contra el plan de estudios oficial del TESH y agregar las demás carreras.
-- ---------------------------------------------------------------
INSERT INTO materias (nombre, carrera, semestre) VALUES
('Cálculo Diferencial', 'Ingeniería en Sistemas Computacionales', 1),
('Fundamentos de Programación', 'Ingeniería en Sistemas Computacionales', 1),
('Matemáticas Discretas', 'Ingeniería en Sistemas Computacionales', 1),
('Taller de Ética', 'Ingeniería en Sistemas Computacionales', 1),
('Fundamentos de Investigación', 'Ingeniería en Sistemas Computacionales', 1),
('Cálculo Integral', 'Ingeniería en Sistemas Computacionales', 2),
('Programación Orientada a Objetos', 'Ingeniería en Sistemas Computacionales', 2),
('Álgebra Lineal', 'Ingeniería en Sistemas Computacionales', 2),
('Probabilidad y Estadística', 'Ingeniería en Sistemas Computacionales', 2),
('Contabilidad Financiera', 'Ingeniería en Sistemas Computacionales', 2),
('Cálculo Vectorial', 'Ingeniería en Sistemas Computacionales', 3),
('Estructura de Datos', 'Ingeniería en Sistemas Computacionales', 3),
('Investigación de Operaciones', 'Ingeniería en Sistemas Computacionales', 3),
('Física General', 'Ingeniería en Sistemas Computacionales', 3),
('Ecuaciones Diferenciales', 'Ingeniería en Sistemas Computacionales', 4),
('Métodos Numéricos', 'Ingeniería en Sistemas Computacionales', 4),
('Tópicos Avanzados de Programación', 'Ingeniería en Sistemas Computacionales', 4),
('Fundamentos de Bases de Datos', 'Ingeniería en Sistemas Computacionales', 4),
('Simulación', 'Ingeniería en Sistemas Computacionales', 4),
('Sistemas Operativos', 'Ingeniería en Sistemas Computacionales', 5),
('Taller de Bases de Datos', 'Ingeniería en Sistemas Computacionales', 5),
('Fundamentos de Ingeniería de Software', 'Ingeniería en Sistemas Computacionales', 5),
('Arquitectura de Computadoras', 'Ingeniería en Sistemas Computacionales', 5),
('Fundamentos de Telecomunicaciones', 'Ingeniería en Sistemas Computacionales', 5),
('Redes de Computadoras', 'Ingeniería en Sistemas Computacionales', 6),
('Ingeniería de Software', 'Ingeniería en Sistemas Computacionales', 6),
('Administración de Bases de Datos', 'Ingeniería en Sistemas Computacionales', 6),
('Lenguajes y Autómatas I', 'Ingeniería en Sistemas Computacionales', 6),
('Lenguajes de Interfaz', 'Ingeniería en Sistemas Computacionales', 6),
('Lenguajes y Autómatas II', 'Ingeniería en Sistemas Computacionales', 7),
('Gestión de Proyectos de Software', 'Ingeniería en Sistemas Computacionales', 7),
('Conmutación y Enrutamiento de Redes', 'Ingeniería en Sistemas Computacionales', 7),
('Sistemas Programables', 'Ingeniería en Sistemas Computacionales', 7),
('Taller de Investigación I', 'Ingeniería en Sistemas Computacionales', 7),
('Programación Web', 'Ingeniería en Sistemas Computacionales', 8),
('Programación Lógica y Funcional', 'Ingeniería en Sistemas Computacionales', 8),
('Administración de Redes', 'Ingeniería en Sistemas Computacionales', 8),
('Taller de Investigación II', 'Ingeniería en Sistemas Computacionales', 8);
