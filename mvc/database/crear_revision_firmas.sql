-- MIGRACIÓN DE PRUEBA. Ejecutar una vez en maderas7_informes mediante phpMyAdmin.
-- No elimina ni modifica datos de las tablas actuales.
-- Autorizar revisores explícitamente (sentencia indicada al final).
CREATE TABLE IF NOT EXISTS informe_revisores (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  habilitado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informe_revisiones (
  informe_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  estado ENUM('pendiente','devuelto','aprobado') NOT NULL DEFAULT 'pendiente',
  solicitante_id BIGINT UNSIGNED NOT NULL,
  fecha_solicitud DATETIME NOT NULL,
  revisor_id BIGINT UNSIGNED DEFAULT NULL,
  fecha_revision DATETIME DEFAULT NULL,
  motivo_devolucion VARCHAR(500) DEFAULT NULL,
  hash_datos CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  firma_png MEDIUMBLOB DEFAULT NULL,
  KEY idx_revision_estado (estado,fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informe_revision_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  informe_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  accion ENUM('solicitar','devolver','aprobar','corregir') NOT NULL,
  observacion VARCHAR(500) DEFAULT NULL,
  hash_datos CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_eventos_informe (informe_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PASO APARTE (sustituye el correo por el usuario que efectivamente revisará):
-- INSERT INTO informe_revisores (usuario_id,habilitado)
-- SELECT id,1 FROM usuarios WHERE email='CORREO_DEL_REVISOR' AND activo=1
-- ON DUPLICATE KEY UPDATE habilitado=1;
