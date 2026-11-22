-- Controlab: perfiles internos. Ejecutar UNA VEZ en cPanel/phpMyAdmin → maderas7_informes.
-- No se modifican ni eliminan usuarios o informes existentes.
-- El perfil es una tabla aparte para no alterar la tabla de login usuarios.
CREATE TABLE IF NOT EXISTS usuario_perfiles (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  rol ENUM('administrador','administrativo','laboratorista','digitador') NOT NULL,
  version_credenciales INT UNSIGNED NOT NULL DEFAULT 1,
  fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_perfil_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PASO IMPRESCINDIBLE: convertir EXPLÍCITAMENTE la cuenta existente en administrador.
-- 1) Ver las cuentas:
--    SELECT id,email,activo FROM usuarios ORDER BY id;
-- 2) Reemplazar el correo en la sentencia siguiente por la cuenta actual (sin compartir contraseñas).
-- IMPORTANTE: no ejecutar este INSERT literalmente con el marcador.
--
-- INSERT INTO usuario_perfiles (usuario_id,nombre,rol)
-- SELECT id,'Administrador principal','administrador'
-- FROM usuarios WHERE email='CORREO_ADMINISTRADOR_EXISTENTE' AND activo=1
-- ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),rol='administrador';
--
-- 3) Comprobar que existe un administrador activo ANTES del despliegue:
-- SELECT u.id,u.email,p.nombre,p.rol
-- FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id
-- WHERE u.activo=1 AND p.rol='administrador';
--
-- La aplicación falla cerrada sin perfil. No convertir automáticamente a todos en administradores.
