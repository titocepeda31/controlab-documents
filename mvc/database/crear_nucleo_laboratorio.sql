-- Controlab: núcleo técnico piloto. Ejecutar DESPUÉS de vincular_servicios_ensayos.sql.
-- No altera ni borra datos anteriores. Catálogo de referencia, NO alcance acreditado.
CREATE TABLE IF NOT EXISTS lab_ensayos_catalogo (
 codigo VARCHAR(8) PRIMARY KEY, nombre VARCHAR(180) NOT NULL, familia VARCHAR(80) NOT NULL,
 captura_piloto TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_materiales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, obra_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(180) NOT NULL, creado_por BIGINT UNSIGNED NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_material (obra_id,nombre), FOREIGN KEY (obra_id) REFERENCES obras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_muestras (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, material_id BIGINT UNSIGNED NOT NULL,
 codigo VARCHAR(80) NOT NULL, fecha DATE NOT NULL, creado_por BIGINT UNSIGNED NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_muestra (material_id,codigo), FOREIGN KEY(material_id) REFERENCES lab_materiales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_puntos_control (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, material_id BIGINT UNSIGNED NOT NULL,
 ot_id BIGINT UNSIGNED NOT NULL, codigo VARCHAR(80) NOT NULL, creado_por BIGINT UNSIGNED NOT NULL,
 creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_punto (ot_id,codigo),
 FOREIGN KEY(material_id) REFERENCES lab_materiales(id), FOREIGN KEY(ot_id) REFERENCES ot_ordenes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_ejecuciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 muestra_id BIGINT UNSIGNED NULL, punto_id BIGINT UNSIGNED NULL,
 ensayo_codigo VARCHAR(8) NOT NULL, fecha DATE NOT NULL,
 creado_por BIGINT UNSIGNED NOT NULL, creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(muestra_id) REFERENCES lab_muestras(id), FOREIGN KEY(punto_id) REFERENCES lab_puntos_control(id),
 FOREIGN KEY(ensayo_codigo) REFERENCES lab_ensayos_catalogo(codigo),
 CHECK ((muestra_id IS NULL) <> (punto_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_resultados (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ejecucion_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, estado ENUM('borrador','aprobado','reemplazado','anulado') NOT NULL DEFAULT 'borrador',
 datos LONGTEXT NULL, creado_por BIGINT UNSIGNED NOT NULL, creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_resultado_version (ejecucion_id,version), FOREIGN KEY(ejecucion_id) REFERENCES lab_ejecuciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_estudios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, material_id BIGINT UNSIGNED NOT NULL,
 codigo VARCHAR(80) NOT NULL, origen ENUM('propio','cliente','proveedor') NOT NULL,
 estado ENUM('borrador','aprobado','reemplazado','anulado') NOT NULL DEFAULT 'borrador',
 creado_por BIGINT UNSIGNED NOT NULL, creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_estudio (material_id,codigo), FOREIGN KEY(material_id) REFERENCES lab_materiales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_estudio_resultados (
 estudio_id BIGINT UNSIGNED NOT NULL, resultado_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(estudio_id,resultado_id), FOREIGN KEY(estudio_id) REFERENCES lab_estudios(id),
 FOREIGN KEY(resultado_id) REFERENCES lab_resultados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_referencias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, estudio_id BIGINT UNSIGNED NOT NULL,
 metodo ENUM('proctor','relativa') NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 estado ENUM('borrador','aprobado','reemplazado','anulado') NOT NULL DEFAULT 'borrador',
 datos LONGTEXT NULL, creado_por BIGINT UNSIGNED NOT NULL, creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_lab_ref (estudio_id,metodo,version), FOREIGN KEY(estudio_id) REFERENCES lab_estudios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_referencia_resultados (
 referencia_id BIGINT UNSIGNED NOT NULL, resultado_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(referencia_id,resultado_id), FOREIGN KEY(referencia_id) REFERENCES lab_referencias(id),
 FOREIGN KEY(resultado_id) REFERENCES lab_resultados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_control_referencia (
 ejecucion_id BIGINT UNSIGNED PRIMARY KEY, referencia_id BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(ejecucion_id) REFERENCES lab_ejecuciones(id), FOREIGN KEY(referencia_id) REFERENCES lab_referencias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lab_informe_requisitos (
 codigo VARCHAR(12) PRIMARY KEY, nombre VARCHAR(180) NOT NULL,
 requisitos VARCHAR(500) NOT NULL, condicion_tecnica VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- No habilita emisión oficial: reglas descriptivas pendientes de validación.
INSERT IGNORE INTO lab_ensayos_catalogo(codigo,nombre,familia,captura_piloto) VALUES
('S01','Granulometría','Caracterización',1),
('S02','Humedad','Caracterización',1),
('S03','Límite líquido','Caracterización',1),
('S04','Límite plástico','Caracterización',1),
('S05','Densidad de partículas sólidas','Caracterización',0),
('S06','Límite de contracción','Caracterización',0),
('S07','Sedimentación','Caracterización',0),
('S08','Materia orgánica','Caracterización',0),
('S09','Proctor normal','Compactación',1),
('S10','Proctor modificado','Compactación',1),
('S11','Densidad mínima','Compactación',1),
('S12','Densidad máxima','Compactación',1),
('S13','CBR','Capacidad de soporte',1),
('S14','Densidad nuclear','Terreno',1),
('S15','Cono de arena','Terreno',1),
('S16','Calicata','Exploración',0),
('S17','Porchet','Exploración',0),
('S18','Penetrómetro dinámico','Terreno',0),
('S19','Placa de carga','Terreno',0),
('S20','Cubicidad de partículas','Áridos',0),
('S21','Desgaste Los Ángeles','Áridos',0),
('S22','Equivalente de arena','Áridos',0),
('S23','Material fino menor a 0,080 mm','Áridos',0),
('S24','Absorción de agua','Áridos',0),
('S25','Índice de lajas','Áridos',0),
('S26','Densidad de áridos','Áridos',0),
('S27','Desintegración por sulfatos','Áridos',0),
('S28','Corte directo','Geotecnia',0),
('S29','Triaxial','Geotecnia',0),
('S30','Compresión no confinada','Geotecnia',0),
('S31','Consolidación','Geotecnia',0),
('S32','Permeabilidad','Geotecnia',0),
('S33','Hinchamiento','Geotecnia',0),
('S34','Colapso','Geotecnia',0);
INSERT IGNORE INTO lab_informe_requisitos(codigo,nombre,requisitos,condicion_tecnica) VALUES
('INF-01','Granulometría','S01','Requiere granulometría completa, no solo tamices piloto.'),
('INF-02','Plasticidad','S03 + S04 + C01','Misma muestra; tratar casos no plásticos.'),
('INF-03','Clasificación USCS','S01 + C03','Plasticidad según fracción y algoritmo normativo.'),
('INF-04','Clasificación AASHTO','S01 + C04','Plasticidad y tamices según grupo.'),
('INF-05','Proctor normal','S09','Curva y método validados.'),
('INF-06','Proctor modificado','S10','Curva y método validados.'),
('INF-07','Densidades máxima y mínima','S11 + S12','Misma referencia de material.'),
('INF-08','CBR','S13','Condiciones de preparación y expansión según método.'),
('INF-09','Control de compactación','(S14 o S15) + C05 + referencia Proctor + C06','Referencia aprobada y aplicable por punto.'),
('INF-10','Control por densidad relativa','(S14 o S15) + C05 + referencias mínima y máxima + C07','Método definido técnicamente.'),
('INF-11','Calicatas','S16','Estratigrafía y trazabilidad.'),
('INF-12','Infiltración','S17','Procedimiento y unidades.'),
('INF-13','Calidad de áridos','S20–S27 según servicio','Selección contractual y normativa.'),
('INF-14','Caracterización de suelo','Según servicio y método','Conjunto de ensayos contratados.'),
('INF-15','Estudio de mecánica de suelos','Según proyecto','Requiere análisis profesional; no automático.'),
('INF-16','Resistencia mecánica','S28 o S29 o S30','Según ensayo.'),
('INF-17','Consolidación','S31','Parámetros completos.'),
('INF-18','Permeabilidad','S32','Método y condiciones.');
