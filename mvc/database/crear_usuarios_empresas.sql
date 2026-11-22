-- Controlab MVC: perfiles y accesos segregados por empresa.
-- Ejecutar en maderas7_informes antes del despliegue. No cambia usuarios existentes.
CREATE TABLE IF NOT EXISTS usuario_accesos (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  rol ENUM('administrador','laboratorista','digitalizador','cliente') NOT NULL,
  empresa_id BIGINT UNSIGNED DEFAULT NULL,
  fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_perfiles_empresa (empresa_id,rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informe_destinatarios (
  informe_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  empresa_id BIGINT UNSIGNED NOT NULL,
  asignado_por BIGINT UNSIGNED NOT NULL,
  fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_destinatarios_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANTE: autorizar el administrador existente como paso aparte.
-- Reemplaza el correo por el usuario administrador de tu instalación:
-- INSERT INTO usuario_accesos (usuario_id,nombre,rol,empresa_id)
-- SELECT id,'Administrador Controlab','administrador',NULL
-- FROM usuarios WHERE email='admin@tudominio.cl' AND activo=1
-- ON DUPLICATE KEY UPDATE rol='administrador',empresa_id=NULL;
--
-- Ningún usuario que no tenga perfil podrá ver informes después del despliegue.
-- Un usuario 'cliente' solo ve informes APROBADOS vinculados a su empresa
-- mediante informe_destinatarios. La vinculación nunca se infiere del texto
-- ingresado en los campos Contratista o Mandante.