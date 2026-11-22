-- Controlab - Ubicacion validada de obras y busqueda cartografica controlada.
-- Ejecutar en phpMyAdmin maderas7_informes ANTES de desplegar. No modifica obras ni OT existentes.
CREATE TABLE IF NOT EXISTS obra_ubicaciones (
  obra_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  latitud DECIMAL(10,7) NOT NULL,
  longitud DECIMAL(10,7) NOT NULL,
  fuente ENUM('busqueda','mapa_manual') NOT NULL DEFAULT 'mapa_manual',
  actualizado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_geo_obra FOREIGN KEY (obra_id) REFERENCES obras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache de busquedas puntuales. No se consulta la API publica al teclear.
CREATE TABLE IF NOT EXISTS geo_busquedas_cache (
  clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  respuesta LONGTEXT NOT NULL,
  actualizado_el TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite global por instalacion: maximo 1 peticion por segundo al servicio publico.
CREATE TABLE IF NOT EXISTS geo_nominatim_limite (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  ultimo_contacto_ms BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO geo_nominatim_limite (id,ultimo_contacto_ms)
VALUES (1,0) ON DUPLICATE KEY UPDATE id=id;
