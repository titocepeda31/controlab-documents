-- Ejecutar antes de desplegar. No modifica datos ni tarifas existentes.
CREATE TABLE IF NOT EXISTS servicio_ensayos (
 servicio_id BIGINT UNSIGNED NOT NULL,
 ensayo_codigo VARCHAR(24) NOT NULL,
 PRIMARY KEY (servicio_id, ensayo_codigo),
 CONSTRAINT fk_servicio_ensayos_catalogo FOREIGN KEY (servicio_id) REFERENCES catalogo_servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
