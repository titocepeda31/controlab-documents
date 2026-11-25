-- Ejecutar después de crear_vinculo_ensayos_ot.sql. No elimina ni modifica resultados.
CREATE TABLE IF NOT EXISTS lab_tareas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ot_id BIGINT UNSIGNED NOT NULL,
 servicio_id BIGINT UNSIGNED NOT NULL,
 servicio_nombre VARCHAR(180) NOT NULL,
 grupo VARCHAR(24) NOT NULL,
 ordinal INT UNSIGNED NOT NULL,
 area ENUM('laboratorio','terreno') NOT NULL,
 alternativas VARCHAR(100) NOT NULL,
 ejecucion_id BIGINT UNSIGNED NULL,
 creado_por BIGINT UNSIGNED NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_tarea(ot_id,servicio_id,grupo,ordinal),
 UNIQUE KEY uq_lab_tarea_ejecucion(ejecucion_id),
 KEY idx_lab_tarea_area(ot_id,area),
 FOREIGN KEY(ot_id) REFERENCES ot_ordenes(id),
 FOREIGN KEY(servicio_id) REFERENCES catalogo_servicios(id),
 FOREIGN KEY(ejecucion_id) REFERENCES lab_ejecuciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
