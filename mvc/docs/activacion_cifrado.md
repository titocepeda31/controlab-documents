# Activación segura del cifrado de informes — Forestal 3M

**Estado (2026-11-22): código preparado en rama `feature/cifrado-informes`; NO desplegado al sitio público.**
El sistema publicado sigue guardando datos como antes hasta que se ejecute el procedimiento de migración.

## Qué queda cifrado en el código nuevo

- **MySQL:** contenido de `informe_datos.datos` con AES-256-GCM en PHP/OpenSSL, con nonce aleatorio distinto en cada guardado y autenticación del ID de informe.
- **MySQL:** contenido binario de `informe_revisiones.firma_png` con cifrado separado y autenticación por ID.
- **Navegador:** datos persistidos en IndexedDB en `drafts`, `pending`, `history` y `reports`, con AES-GCM/Web Crypto antes de cada guardado y migración de registros legados cuando el usuario abra la nueva versión. Cada usuario tiene una clave AES no exportable conservada por el navegador.
- **No queda cifrado a nivel de aplicación:** índices y metadatos de búsqueda de las tablas (IDs, empresa, número/fecha/estado), motivos y eventos de revisión; estos requieren permisos restrictivos y cifrado de disco/backups. La clave local del navegador **está en el mismo perfil del navegador** y la protección no resiste a alguien con acceso al sitio y al perfil desbloqueado, ni ataques XSS. Para seguridad local más fuerte habría que exigir un secreto de desbloqueo que no se almacene.

## Ruta de desarrollo SIN SSH (cPanel)

Este procedimiento no requiere acceso SSH. **No eliminar registros de prueba sin consentimiento explícito**, aunque el entorno sea de desarrollo. La clave privada ya configurada debe mantenerse fuera de GitHub y no copiarse en capturas de pantalla.

### Paso 1: inventario desde phpMyAdmin (solo lectura)

Abrir **cPanel → phpMyAdmin → base maderas7_informes → SQL** y ejecutar:

```sql
SELECT
  (SELECT COUNT(*) FROM informe_datos) AS total_informes,
  (SELECT COUNT(*) FROM informe_datos WHERE datos NOT LIKE 'enc:v1:%') AS informes_sin_cifrar,
  (SELECT COUNT(*) FROM informe_revisiones WHERE firma_png IS NOT NULL AND LENGTH(firma_png)>0) AS total_firmas,
  (SELECT COUNT(*) FROM informe_revisiones
   WHERE firma_png IS NOT NULL AND LENGTH(firma_png)>0 AND LEFT(firma_png,7) <> 'enc:v1:') AS firmas_sin_cifrar;
```

También revisar en **Estructura** el tipo de `informe_datos.datos` y `informe_revisiones.firma_png`. Si no son suficientemente amplios, ampliarlos tras exportar un respaldo. No subir archivos o capturas con contraseñas.

### Paso 2: elegir la ruta

- **Si informes_sin_cifrar = 0 y firmas_sin_cifrar = 0:** no hay registros legados pendientes; después de ampliar columnas y verificar la clave se puede desplegar la rama de cifrado sin ejecutar el migrador.
- **Si hay registros existentes que se quieren conservar:** usar **Trabajos de cron** de cPanel para ejecutar una sola vez el migrador mediante PHP CLI; esto **no es SSH**. Se programa durante una ventana de mantenimiento y se elimina la tarea al terminar. Primero ejecutar `--dry-run` y comprobar resultados; luego `--apply --maintenance-confirmed --backup-confirmed`, con respaldos probados. Utilizar la ruta real del ejecutable PHP que soporte el hosting y el clon correcto del repositorio.
- **Si los informes de demostración no se necesitan:** solo el responsable puede autorizar expresamente que se descarten. No sugerir `TRUNCATE`/borrado sin confirmar relaciones, integridad de tablas y respaldo.

Ejemplo de **comando de Cron para revisión**, sujeto a confirmar ruta PHP y que el clon use `feature/cifrado-informes`:

```sh
/usr/local/bin/php /home/maderas7/repositories/controlab-documents/mvc/backend/scripts/encrypt_existing_reports.php /home/maderas7/demo-informes/config.php --dry-run
```

No asumir que `/usr/local/bin/php` es la ruta válida: puede variar entre proveedores y versiones de cPanel. En cron no introducir la clave; el programa la leerá del `config.php` privado. Para esta tarea desactivar las notificaciones por correo si podrían exponer información sensible, y usar un log privado de permisos restringidos para verificar la ejecución. Tras aplicar la migración, eliminar el cron.

### Paso 3: desplegar y verificar

La versión del cifrado está en la rama **`feature/cifrado-informes`**, separada de los cambios de usuarios y planificador. El mecanismo de Git de cPanel debe tomar expresamente esa rama; si no permite cambiarla, no asumir que actualizar desde `main` instala el cifrado.

Después de migrar, desplegar con el script existente de forma controlada. La protección del script impide instalar si detecta registros sin cifrar, clave ausente o columnas pequeñas. Comprobar en phpMyAdmin que **solamente el contenido técnico** comience por `enc:v1:`, no que las claves aparezcan en la base; no copiar textos descifrados en chats ni capturas.

## Requisitos antes del despliegue

1. **No trabajar sobre los datos reales sin respaldo restaurable.** Respaldar la base de datos y los archivos privados; proteger el respaldo, que contendrá texto sin cifrar. Verificar que puede restaurarse.
2. **Bloquear los guardados temporalmente (mantenimiento)** desde antes de cifrar los registros hasta que el nuevo servidor esté funcionando. El servidor viejo no entiende los datos cifrados; si se migra con el sistema viejo activo, puede fallar o perderse un guardado.
3. El administrador del cPanel crea una clave **única, aleatoria**, sin pegarla en el chat, GitHub ni emails:
   `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`
   Se configura en `/home/maderas7/demo-informes/config.php` privado como `'data_encryption_key' => 'VALOR_GENERADO'`, separada de la base de datos. Respaldar la clave **en lugar independiente y protegido**, o los documentos no podrán recuperarse.
4. Verificar extensión PHP `openssl`; disponer de MySQL con InnoDB y PHP en CLI.
5. En phpMyAdmin, verificar tipos de columna. Si `informe_datos.datos` es `TEXT` u otro tipo pequeño, ampliarlo a `MEDIUMTEXT`. Si `informe_revisiones.firma_png` es `BLOB`, ampliarlo a `MEDIUMBLOB`. Por ejemplo, después de verificar la estructura existente y sus restricciones:
   ```sql
   ALTER TABLE informe_datos MODIFY COLUMN datos MEDIUMTEXT NOT NULL;
   ALTER TABLE informe_revisiones MODIFY COLUMN firma_png MEDIUMBLOB NULL;
   ```
   No ejecutar estos SQL a ciegas sin comprobar valores por defecto, restricciones y respaldos.
6. **Esta rama parte del sistema actual (`main`) y no requiere instalar el módulo de usuarios o planificador pendiente.** Sí exige que las tablas de informes y revisión existentes estén presentes.
7. Realizar primero el ensayo en una **copia de la base** con credenciales y clave diferentes de producción, incluyendo informes con firma aprobada.

## Orden estricto en una ventana de mantenimiento

Desde el clon de la rama que contiene el cifrado, ejecutar:
```bash
php mvc/backend/tests/ReportCryptoTest.php
php mvc/backend/scripts/encrypt_existing_reports.php /home/maderas7/demo-informes/config.php --dry-run
```
Si la revisión no muestra anomalías, el responsable confirma respaldo probado y mantenimiento:
```bash
php mvc/backend/scripts/encrypt_existing_reports.php /home/maderas7/demo-informes/config.php --apply --maintenance-confirmed --backup-confirmed
```
El script es idempotente, verifica hashes de informes aprobados y autenticación de contenido, cifra resultados y firmas, y falla ante contenido roto. **No es un comando a ejecutar mientras los trabajadores siguen usando el sistema antiguo.**

**Inmediatamente después**, desplegar versión compatible con cifrado en el cPanel, antes de reabrir el servicio. El script `scripts/cpanel_mvc_deploy.sh` bloquea el despliegue si falta la clave, no hay OpenSSL, las columnas son pequeñas o aún quedan resultados/firmas sin cifrar. **No desplegar solo el frontend o solo el backend.**

## Verificación posterior

1. Guardar un informe ficticio con la nueva versión y comprobar que `informe_datos.datos` comienza por `enc:v1:`, sin resultados legibles.
2. Abrirlo y constatar que los valores son idénticos.
3. Aprobar un informe de prueba, revisar la firma y su `integridad_verificada`.
4. Guardar un borrador sin internet, recargar, comprobar recuperación, reintentar sincronización sin duplicar registros. En herramientas del navegador, `IndexedDB` debe contener `ciphertext`, nunca un objeto `datos` en claro.
5. Confirmar que cada guardado produce un texto cifrado distinto y que un usuario sin permiso no puede leerlo.
6. Confirmar que un respaldo protegido se puede restaurar junto a la clave. Planificar rotación de clave y gestión de acceso si el sistema crece.

## Advertencias

- Los **backups históricos** y las copias anteriores del navegador **no se cifran retroactivamente**; requieren tratamiento seguro y política de retención.
- El correo electrónico y el PDF final son etapas separadas y pueden volver a exponer datos descifrados si se guardan como adjuntos o archivos públicos.
- La clave del servidor no debe almacenarse en GitHub ni servirse al navegador.
- La autenticación en el frontend y el acceso al equipo siguen importando: cifrado local con clave guardada en el propio navegador **no es cifrado de extremo a extremo**.
- Si una clave se extravía o se cambia sin re-cifrar todos los registros, los datos podrían perderse de forma irrecuperable.
