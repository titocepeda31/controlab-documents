-- Ejecutar una sola vez en phpMyAdmin, base de datos maderas7_informes.
-- Mantiene intactas las tablas existentes; permite evitar inserciones duplicadas al sincronizar.
CREATE TABLE IF NOT EXISTS informe_envios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  client_uuid CHAR(36) NOT NULL,
  informe_id BIGINT UNSIGNED NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uniq_usuario_envio (usuario_id, client_uuid),
  KEY idx_informe (informe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
