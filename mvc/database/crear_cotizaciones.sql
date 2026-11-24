-- Ejecutar una sola vez antes de desplegar. No elimina datos.
ALTER TABLE catalogo_servicios ADD COLUMN precio_clp DECIMAL(12,0) UNSIGNED NULL, ADD COLUMN precio_uf DECIMAL(16,4) UNSIGNED NULL;
CREATE TABLE cotizaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 numero VARCHAR(40) NOT NULL UNIQUE,
 obra_id BIGINT UNSIGNED NOT NULL,
 moneda ENUM('CLP','UF') NOT NULL,
 estado ENUM('borrador','enviada','aprobada','rechazada','vencida') NOT NULL DEFAULT 'borrador',
 vigencia DATE NULL,
 condiciones TEXT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE cotizacion_servicios (
 cotizacion_id BIGINT UNSIGNED NOT NULL,
 servicio_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(180) NOT NULL,
 unidad VARCHAR(60) NOT NULL,
 cantidad INT UNSIGNED NULL,
 precio_unitario DECIMAL(16,4) UNSIGNED NOT NULL,
 PRIMARY KEY(cotizacion_id,servicio_id),
 FOREIGN KEY(cotizacion_id) REFERENCES cotizaciones(id),
 FOREIGN KEY(servicio_id) REFERENCES catalogo_servicios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE ot_ordenes ADD COLUMN cotizacion_id BIGINT UNSIGNED NULL, ADD CONSTRAINT fk_ot_cotizacion FOREIGN KEY(cotizacion_id) REFERENCES cotizaciones(id);
