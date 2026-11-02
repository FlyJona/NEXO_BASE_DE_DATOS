-- V003: datos iniciales.
-- Clasificación de los datos de nexo/database/nexo.sql e instalar.php:
--   * Referencia indispensable: catálogo de materias -> se carga aquí.
--     Es un EJEMPLO (Ingeniería en Sistemas Computacionales) pendiente de validar con el plan del TESH.
--   * Demostración: 4 cuentas ficticias, 2 publicaciones y 1 respuesta -> NO van aquí.
--     Necesitan password_hash() de PHP; se crean con nexo/instalar.php solo en modo desarrollo.
--   * Datos personales o productivos: ninguno se versiona.
-- Idempotente: si una materia ya existe (misma carrera y nombre, sin distinguir mayúsculas) se omite.

SET LOCAL search_path TO nexo, public;

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
('Taller de Investigación II', 'Ingeniería en Sistemas Computacionales', 8)
ON CONFLICT (lower(nombre), lower(carrera)) DO NOTHING;
