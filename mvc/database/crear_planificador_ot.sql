-- Controlab — Planificador de órdenes de trabajo (OT).
-- Instalar en phpMyAdmin sobre la misma base usada por Controlab.
-- Solo crea tablas nuevas: NO modifica ni elimina informes, usuarios ni firmas.
-- DATETIME conserva horarios locales Chile (America/Santiago) sin conversión automática.
CREATE TABLE IF NOT EXISTS ot_ordenes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero_ot VARCHAR(40) NULL,
  cliente VARCHAR(160) NOT NULL,
  obra VARCHAR(180) NOT NULL,
  destino VARCHAR(240) NOT NULL,
  comuna VARCHAR(120) NOT NULL DEFAULT '',
  actividad VARCHAR(200) NOT NULL,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  estado ENUM('planificada','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'planificada',
  prioridad ENUM('normal','urgente') NOT NULL DEFAULT 'normal',
  observaciones TEXT NULL,
  creado_por BIGINT UNSIGNED NOT NULL,
  actualizado_por BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  creado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ot_numero (numero_ot),
  KEY idx_ot_fechas (inicio,fin),
  KEY idx_ot_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ot_asignaciones (
  ot_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  asignado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ot_id,usuario_id),
  KEY idx_ot_persona (usuario_id,ot_id),
  CONSTRAINT fk_ot_asignacion_orden FOREIGN KEY (ot_id) REFERENCES ot_ordenes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ot_asignacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ot_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ot_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  accion ENUM('crear','actualizar') NOT NULL,
  resumen VARCHAR(500) NOT NULL,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ot_eventos (ot_id,fecha),
  CONSTRAINT fk_ot_evento_orden FOREIGN KEY (ot_id) REFERENCES ot_ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
