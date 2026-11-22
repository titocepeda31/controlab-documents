-- Forestal 3M (demostración): una firma visual por usuario.
-- Ejecutar una vez en phpMyAdmin, base maderas7_informes.
-- No modifica ni borra informes existentes.
CREATE TABLE IF NOT EXISTS laboratorista_firmas (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  firma_png MEDIUMBLOB NOT NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Firma almacenada con AES-256-GCM y AAD ligado a usuario_id.
-- No es una firma avanzada ni implica aprobación de cada informe.
