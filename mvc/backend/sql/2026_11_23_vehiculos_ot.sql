-- Ejecutar en phpMyAdmin ANTES de desplegar el código de vehículos.
CREATE TABLE IF NOT EXISTS catalogo_vehiculos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 patente VARCHAR(12) NOT NULL UNIQUE,
 descripcion VARCHAR(120) NOT NULL DEFAULT '',
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ot_vehiculos (
 ot_id INT UNSIGNED NOT NULL PRIMARY KEY,
 vehiculo_id INT UNSIGNED NOT NULL,
 INDEX idx_ot_vehiculos_vehiculo (vehiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Sin FK por compatibilidad con tipos de ID existentes en la instalación.
