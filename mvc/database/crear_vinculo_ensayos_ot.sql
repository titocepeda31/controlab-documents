-- Ejecutar después del núcleo técnico y catálogo de materiales.
-- No modifica ni vincula retrospectivamente resultados existentes.
CREATE TABLE IF NOT EXISTS lab_ejecucion_ot (
 ejecucion_id BIGINT UNSIGNED PRIMARY KEY,
 ot_id BIGINT UNSIGNED NOT NULL,
 servicio_id BIGINT UNSIGNED NOT NULL,
 servicio_nombre VARCHAR(180) NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_lab_ejecucion_ot (ot_id,servicio_id),
 FOREIGN KEY (ejecucion_id) REFERENCES lab_ejecuciones(id),
 FOREIGN KEY (ot_id) REFERENCES ot_ordenes(id),
 FOREIGN KEY (servicio_id) REFERENCES catalogo_servicios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
