# Módulo de usuarios y perfiles de Controlab — Forestal 3M

Código de desarrollo preparado en `feature/modulo-usuarios-roles`. **No está activo en cPanel hasta ejecutar SQL y desplegar.**

## Perfiles y permisos

| Perfil | Registrar/consultar informes | Administrar usuarios | Firma visual del laboratorista | Revisar/aprobar |
|---|---|---|---|---|
| **Administrador** | Sí | Sí: altas, edición, desactivar, restablecer claves | No | Solo si está incluido en `informe_revisores` |
| **Administrativo** | Sí | No | No | Solo si está incluido en `informe_revisores` |
| **Laboratorista** | Sí | No | Sí, una firma reutilizable cifrada por cuenta | Solo si está incluido en `informe_revisores` |
| **Digitador** | Sí | No | No | No; sí puede solicitar revisión de sus propios informes |

- Los usuarios pueden crear informes y corregir **sus propios** registros no aprobados; de momento no hay módulo de asignación o traspaso formal entre laboratorista y digitador.
- Todos los perfiles son **personal interno** y pueden consultar informes internos. **No hay todavía acceso de clientes o segregación por empresa/obra.**
- Un informe creado por un digitador no lleva automáticamente la firma de otro laboratorista, porque aún no hay una relación formal entre ambos perfiles en el reporte. No falsificar atribución de una firma.
- La firma del perfil no constituye aprobación de un documento ni firma electrónica avanzada.
- **Administrador** es una cuenta de configuración; **Administrativo** es personal de oficina con acceso operativo, sin gestión de claves o cuentas.
- Sin perfil asignado o con la cuenta desactivada **no hay acceso a la API**. Al cambiar una contraseña, las sesiones anteriores de esa cuenta dejan de funcionar.

## Instalación en cPanel SIN SSH

La migración se ejecuta ANTES del código nuevo. El script de despliegue bloquea la actualización si falta la tabla o no existe un administrador activo.

1. En cPanel → phpMyAdmin → base `maderas7_informes` → SQL, ejecutar únicamente las sentencias **CREATE TABLE** del archivo `mvc/database/crear_perfiles_usuarios.sql`:

```sql
CREATE TABLE IF NOT EXISTS usuario_perfiles (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  rol ENUM('administrador','administrativo','laboratorista','digitador') NOT NULL,
  version_credenciales INT UNSIGNED NOT NULL DEFAULT 1,
  fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_perfil_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

2. **Reconocer la cuenta administrativa existente.** Sin escribir ni compartir la contraseña, identificar el correo real:

```sql
SELECT id,email,activo FROM usuarios ORDER BY id;
```

3. Sustituir SOLO `CORREO_ADMINISTRADOR_EXISTENTE` por el email real del administrador y ejecutar:

```sql
INSERT INTO usuario_perfiles (usuario_id,nombre,rol)
SELECT id,'Administrador principal','administrador'
FROM usuarios WHERE email='CORREO_ADMINISTRADOR_EXISTENTE' AND activo=1
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),rol='administrador';
```

No ejecutar la sentencia con el marcador sin reemplazar. Debe afectar exactamente a la cuenta que se quiere promover. En caso de dudas, detener el proceso.

4. Confirmar que hay al menos un admin activo:

```sql
SELECT u.id,u.email,p.nombre,p.rol FROM usuarios u
JOIN usuario_perfiles p ON p.usuario_id=u.id
WHERE u.activo=1 AND p.rol='administrador';
```

5. Actualizar código en cPanel **Git Version Control → Update from Remote → Deploy HEAD Commit**. El despliegue verifica `usuario_perfiles` y la existencia de un admin activo **antes de sustituir archivos**.
6. Cerrar sesión e ingresar nuevamente para inicializar versión de credenciales y perfil. Abrir `https://maderasyferreteriasmuena.cl/demo/formulario.html` → ☰ → **Usuarios** (solo administradores), o directamente `/demo/usuarios.html`. Crear cuentas con nombre, correo, perfil y clave inicial de mínimo 12 caracteres. La interfaz muestra una copia temporal para entregarla privadamente.

**Importante**: los usuarios antiguos que no reciban perfil todavía no podrán iniciar sesión después de este cambio. El administrador puede asignarles perfil desde la lista, sin reescribir sus claves previas. Conviene hacer la asignación antes de habilitar nuevamente el trabajo a otras personas.

## Seguridad y limitaciones

- Solo el administrador puede llamar a `GET/POST /api/usuarios`, `PATCH /api/usuarios/{id}`, `POST /api/usuarios/{id}/restablecer-clave`; no depende del botón en pantalla: el backend comprueba el rol en cada solicitud.
- Todas las mutaciones usan sesión + token CSRF y sentencias preparadas. La contraseña se almacena como `password_hash(PASSWORD_DEFAULT)`, nunca recuperable en texto.
- **No se eliminan usuarios**: al desactivar se conservan referencias y autoría de informes. No se puede desactivar/degradar al último administrador activo.
- Antes de desactivar un digitador o laboratorista, comprobar sus borradores pendientes en dispositivos: su cuenta dejará de poder sincronizar.
- Quien conserva un equipo previamente autenticado podrá abrir borradores ya almacenados sin conexión; la revocación de permisos requiere volver a conectar al servidor. Protege el teléfono con bloqueo de pantalla.
- Actualizar y guardar la firma solo está permitido para **laboratorista**, nunca para digitador. Un administrativo solo puede aprobar si está autorizado expresamente como revisor.
- La página de administración funciona **solo con conexión**; no almacena listas de usuarios ni contraseñas en IndexedDB.
- Esta etapa no incluye invitaciones por correo, registros públicos, recuperación automática por email ni registro de auditoría administrativa detallado.
