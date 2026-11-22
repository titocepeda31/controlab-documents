-- Ejecutar tras respaldar la base de datos, antes de desplegar los cambios.
ALTER TABLE empresas
 ADD COLUMN nombre_comercial VARCHAR(160) NULL,
 ADD COLUMN telefono VARCHAR(40) NULL,
 ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1;
