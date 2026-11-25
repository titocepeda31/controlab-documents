-- Estudios de suelo: metadatos y trazabilidad de preparación del piloto.
-- Ejecutar después de crear_nucleo_laboratorio.sql. No modifica estudios previos.
CREATE TABLE IF NOT EXISTS lab_estudio_ficha (
 estudio_id BIGINT UNSIGNED PRIMARY KEY,
 ot_id BIGINT UNSIGNED NULL,
 fecha DATE NOT NULL,
 datos LONGTEXT NULL,
 actualizado_por BIGINT UNSIGNED NOT NULL,
 actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(estudio_id) REFERENCES lab_estudios(id),
 FOREIGN KEY(ot_id) REFERENCES ot_ordenes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_estudio_eventos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 estudio_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 accion VARCHAR(40) NOT NULL,
 fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(estudio_id) REFERENCES lab_estudios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
