-- Ejecutar una sola vez en phpMyAdmin antes de desplegar el módulo Servicios.
-- No modifica informes ni órdenes existentes. Las cantidades son solicitadas, no facturadas.
CREATE TABLE IF NOT EXISTS catalogo_servicios (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(180) NOT NULL,
 unidad VARCHAR(60) NOT NULL DEFAULT 'unidad',
 descripcion VARCHAR(500) NOT NULL DEFAULT '',
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_servicio_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ot_servicios (
 ot_id BIGINT UNSIGNED NOT NULL,
 servicio_id BIGINT UNSIGNED NOT NULL,
 cantidad INT UNSIGNED NOT NULL,
 servicio_nombre VARCHAR(180) NOT NULL,
 unidad VARCHAR(60) NOT NULL,
 creado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (ot_id,servicio_id),
 CONSTRAINT fk_ot_servicio_catalogo FOREIGN KEY (servicio_id) REFERENCES catalogo_servicios(id),
 CONSTRAINT fk_ot_servicio_orden FOREIGN KEY (ot_id) REFERENCES ot_ordenes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
