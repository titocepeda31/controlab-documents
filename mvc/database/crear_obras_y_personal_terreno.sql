-- Controlab: empresas existentes -> obras; personal de terreno SIN usuario de acceso.
-- Ejecutar en phpMyAdmin (base maderas7_informes) ANTES del despliegue.
-- Solo crea tablas nuevas. NO modifica informes, usuarios, OT existentes ni claves.
CREATE TABLE IF NOT EXISTS obras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empresa_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  direccion VARCHAR(240) NOT NULL,
  comuna VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_empresa_obra (empresa_id,nombre),
  KEY idx_obra_empresa (empresa_id,activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_terreno (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  cargo VARCHAR(100) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  observaciones VARCHAR(500) NOT NULL DEFAULT '',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_personal_activo (activo,nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ot_obra_vinculos (
  ot_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  obra_id BIGINT UNSIGNED NOT NULL,
  fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ot_obra (obra_id),
  CONSTRAINT fk_ot_vinculo_obra FOREIGN KEY (obra_id) REFERENCES obras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ot_personal_terreno (
  ot_id BIGINT UNSIGNED NOT NULL,
  personal_id BIGINT UNSIGNED NOT NULL,
  asignado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ot_id,personal_id),
  KEY idx_ot_personal (personal_id,ot_id),
  CONSTRAINT fk_ot_personal_terreno FOREIGN KEY (personal_id) REFERENCES personal_terreno(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los datos de empresa, obra, dirección y comuna existentes en ot_ordenes
-- siguen almacenados sin cambios como instantánea histórica.
-- No se generan accesos, contraseñas ni cuentas en usuarios para personal de terreno.
