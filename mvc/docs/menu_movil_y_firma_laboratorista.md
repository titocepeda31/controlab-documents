# Barra móvil y firma visual del laboratorista — Forestal 3M

## Objetivo
- En teléfonos, la cabecera muestra el nombre del sistema y un menú hamburguesa accesible. Sus opciones son Historial, Revisiones (solo revisores), Mi firma y Salir. El estado de conexión y pendientes sigue disponible bajo la cabecera.
- En la pantalla **Mi firma**, cada usuario autenticado puede dibujar su firma una vez o cargar una imagen PNG/JPG, y guardarla, reemplazarla o eliminarla.
- La firma del perfil es independiente de la firma de **aprobación de revisión**. **No** aprueba automáticamente informes ni constituye firma electrónica avanzada.
- La copia de prueba imprime automáticamente la firma del usuario que registró el informe. La aplicación advierte que no es documento técnico oficial.
- En el dispositivo, la firma descargada se mantiene en IndexedDB cifrado por usuario para impresión de borradores aun sin conexión; en el servidor se cifra con AES-256-GCM, contexto exclusivo `firma_laboratorista` y AAD ligado a `usuario_id`.

## Requisito de cPanel SIN SSH

**Antes** de usar «Deploy HEAD Commit», entrar en **phpMyAdmin → maderas7_informes → SQL** y ejecutar este script del repositorio: `mvc/database/crear_firmas_laboratoristas.sql`.

```sql
CREATE TABLE IF NOT EXISTS laboratorista_firmas (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  firma_png MEDIUMBLOB NOT NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

El despliegue está diseñado para **detenerse sin reemplazar archivos** si falta esta tabla. No hay que borrar ni modificar los informes existentes. Después ejecutar **Git Version Control → Update from Remote → Deploy HEAD Commit**. Probar la pantalla «Mi firma», subir una firma de prueba y generar una impresión de un informe.

## Consideraciones
- La imagen reusable se muestra en copias de prueba incluso de informes anteriores del mismo usuario si este conserva la firma en su perfil. **No existe instantánea histórica por informe**: si se cambia la firma, las futuras impresiones mostrarán la actual. No presentarla como prueba de aprobación ni de autoría irreversible.
- Para informes oficiales o acreditados habría que exigir firma por documento, trazabilidad y mecanismo de aprobación formal.
- El perfil de usuario actual no dispone aún del módulo de roles laboral/cliente. Esta opción se muestra a usuarios autenticados durante el piloto; el uso debe limitarse a cuentas de laboratoristas hasta integrar roles.
- Si el laboratorio requiere que un revisor apruebe un informe, el flujo separado de Revisiones sigue existiendo y no se sustituye por la firma de perfil.
- Para imprimir borradores offline, el usuario debió ingresar antes con conexión para descargar y cifrar localmente su firma.
