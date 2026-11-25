-- Controlab: catálogo comercial inicial (13 servicios propuestos).
-- Ejecutar después de vincular_servicios_ensayos.sql y de las migraciones del piloto.
-- No sobrescribe nombres, unidades, estados, tarifas, históricos ni asociaciones existentes.
-- Configurar tarifas en Servicios antes de crear cotizaciones. No asigna precio cero.
-- Los 13 servicios nuevos se crean activos. Si el nombre ya existe, se conserva su estado.
DROP TEMPORARY TABLE IF EXISTS tmp_cl_servicios;
DROP TEMPORARY TABLE IF EXISTS tmp_cl_ensayos;
DROP TEMPORARY TABLE IF EXISTS tmp_cl_sin_ensayos;
CREATE TEMPORARY TABLE tmp_cl_servicios (
 nombre VARCHAR(180) PRIMARY KEY, unidad VARCHAR(60) NOT NULL, descripcion VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TEMPORARY TABLE tmp_cl_ensayos (
 nombre VARCHAR(180) NOT NULL, codigo VARCHAR(24) NOT NULL, PRIMARY KEY(nombre,codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TEMPORARY TABLE tmp_cl_sin_ensayos (
 servicio_id BIGINT UNSIGNED PRIMARY KEY, nombre VARCHAR(180) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tmp_cl_servicios(nombre,unidad,descripcion) VALUES
('Caracterización de suelo','muestra','Paquete básico sugerido: granulometría, límites de Atterberg y clasificación USCS. No incluye Proctor ni CBR.'),
('Granulometría','ensayo','Distribución granulométrica del material.'),
('Límites de Atterberg','ensayo','Límite líquido, límite plástico e índice de plasticidad.'),
('Proctor normal','ensayo','Compactación con energía normal.'),
('Proctor modificado','ensayo','Compactación con energía modificada.'),
('Densidades máxima y mínima','ensayo','Determinación de ambas densidades de referencia.'),
('CBR','ensayo','Capacidad de soporte del suelo.'),
('Densidad nuclear','punto','Control de densidad en terreno mediante método nuclear.'),
('Densidad por cono de arena','punto','Control de densidad en terreno mediante cono de arena.'),
('Calicata','unidad','Exploración y registro estratigráfico.'),
('Infiltración Porchet','unidad','Ensayo de infiltración.'),
('Cubicidad','ensayo','Forma de partículas.'),
('Desgaste Los Ángeles','ensayo','Resistencia al desgaste.');
INSERT INTO tmp_cl_ensayos(nombre,codigo) VALUES
('Caracterización de suelo','gran'),
('Caracterización de suelo','att'),
('Caracterización de suelo','uscs'),
('Granulometría','gran'),
('Límites de Atterberg','att'),
('Proctor normal','proctor_normal'),
('Proctor modificado','proctor_modificado'),
('Densidades máxima y mínima','maxmin'),
('CBR','cbr'),
('Densidad nuclear','dens_nuclear'),
('Densidad por cono de arena','dens_cono'),
('Calicata','cal'),
('Infiltración Porchet','por'),
('Cubicidad','cubic'),
('Desgaste Los Ángeles','la'),
('Densidad','dens');
START TRANSACTION;
INSERT INTO catalogo_servicios(nombre,unidad,descripcion,activo)
 SELECT s.nombre,s.unidad,s.descripcion,1 FROM tmp_cl_servicios s
 LEFT JOIN catalogo_servicios c ON c.nombre=s.nombre WHERE c.id IS NULL;
-- Solo se inicializan asociaciones si el servicio no tiene ninguna.
-- El servicio antiguo Densidad conserva su método genérico si no estaba configurado.
INSERT INTO tmp_cl_sin_ensayos(servicio_id,nombre)
 SELECT DISTINCT c.id,c.nombre FROM catalogo_servicios c
 JOIN tmp_cl_ensayos t ON t.nombre=c.nombre
 LEFT JOIN servicio_ensayos e ON e.servicio_id=c.id WHERE e.servicio_id IS NULL;
INSERT INTO servicio_ensayos(servicio_id,ensayo_codigo)
 SELECT s.servicio_id,t.codigo FROM tmp_cl_sin_ensayos s JOIN tmp_cl_ensayos t ON t.nombre=s.nombre;
COMMIT;
DROP TEMPORARY TABLE tmp_cl_sin_ensayos;
DROP TEMPORARY TABLE tmp_cl_ensayos;
DROP TEMPORARY TABLE tmp_cl_servicios;
-- Verificación: servicios activos y códigos asociados.
SELECT c.id,c.nombre,c.unidad,c.activo,c.precio_base,c.moneda_base,
 (SELECT GROUP_CONCAT(e.ensayo_codigo ORDER BY e.ensayo_codigo) FROM servicio_ensayos e WHERE e.servicio_id=c.id) AS ensayos
 FROM catalogo_servicios c ORDER BY c.nombre;
