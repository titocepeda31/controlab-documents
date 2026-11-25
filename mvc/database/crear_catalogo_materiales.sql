-- Controlab: catálogo de tipos de materiales y detalle de materiales de obra.
-- Ejecutar después de crear_nucleo_laboratorio.sql. No modifica registros anteriores.
CREATE TABLE IF NOT EXISTS lab_tipos_material (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_por BIGINT UNSIGNED NULL,
 actualizado_por BIGINT UNSIGNED NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_tipo_material (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_material_detalle (
 material_id BIGINT UNSIGNED PRIMARY KEY,
 tipo_id BIGINT UNSIGNED NOT NULL,
 identificacion VARCHAR(75) NOT NULL DEFAULT '',
 procedencia VARCHAR(180) NOT NULL DEFAULT '',
 proveedor VARCHAR(180) NOT NULL DEFAULT '',
 observaciones VARCHAR(500) NOT NULL DEFAULT '',
 creado_por BIGINT UNSIGNED NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (material_id) REFERENCES lab_materiales(id),
 FOREIGN KEY (tipo_id) REFERENCES lab_tipos_material(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo inicial sugerido. Los materiales antiguos no se clasifican por inferencia.
INSERT IGNORE INTO lab_tipos_material(nombre) VALUES
('Suelo natural'),
('Relleno seleccionado'),
('Base granular'),
('Subbase granular'),
('Subrasante'),
('Arena'),
('Grava'),
('Estabilizado'),
('Material de empréstito');
