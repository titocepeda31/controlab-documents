# Seguridad, cifrado y entregas — Controlab

**Estado:** requisitos y propuesta técnica, **no implementado ni desplegado**. No publicar datos de producción en el repositorio. Mantener el piloto Forestal 3M operando como hasta ahora mientras se prueban cambios.

## Pendientes funcionales Forestal 3M

1. El laboratorista Roberto llena los datos del ensayo; el registro debe conservarse una única vez.
2. Roberto puede gestionar su envío o delegarlo a un encargado de envíos; **todavía no está implementado el envío de correo** desde la aplicación.
3. El **formato final del informe técnico y el PDF oficial quedan pendientes** hasta recibir una plantilla verificada. El PDF actual es provisional y la firma visual del piloto no equivale a firma electrónica avanzada.
4. El planificador de OT es el primer módulo funcional del desarrollo; la integración de informes y envíos viene después.

## Evaluación del código actual (rama main, 2026-11-22)

- En `mvc/backend/src/Models/Informe.php`, `create` y `update` almacenan `json_encode($data)` directamente en `informe_datos.datos`; `find` devuelve ese JSON tras `json_decode`. **No hay cifrado de contenido en la aplicación**.
- En `mvc/frontend/formulario.html`, `persistDraft`, `storagePut('pending',...)`, el historial y `storagePut('reports',...)` guardan objetos con datos directamente en **IndexedDB**; **no hay cifrado de contenido local**.
- Se utiliza HTTPS en la URL del hosting y la aplicación prepara opciones seguras de sesión, pero **HTTPS protege el transporte, no garantiza cifrado de registros ya guardados**.
- No se ha evaluado el cifrado físico o de backups ofrecido por el proveedor de hosting: no darlo por hecho.
- Las contraseñas de usuarios se deben manejar con hash de contraseña (password_hash / password_verify), **no con cifrado reversible**.

## Objetivo

Cada vez que se **guarde o actualice** un registro técnico:
- En el servidor, **cifrar los campos técnicos antes de escribirlos en MySQL**; descifrarlos solo en rutas autenticadas y autorizadas para ese documento.
- En el navegador, **cifrar antes de escribir contenido técnico en IndexedDB**: borrador actual, pendientes de sincronización y copias de informes descargados. No almacenar en texto claro informes completos en LocalStorage.
- Cifrar copias de respaldo y proteger copias antiguas, exportaciones y descargas según su clasificación; **los backups viejos con datos sin cifrar siguen siendo un riesgo**.
- **No cifrar indiscriminadamente los índices** necesarios para empresa, obra, número de OT/correlativo, fechas, estados y búsqueda; minimizar los metadatos visibles y aplicar permisos estrictos. Si un dato de identificación es sensible, valorar cifrar su contenido y utilizar índices de búsqueda apropiados.
- Las claves de cifrado deben permanecer **fuera del repositorio, del webroot y de la propia tabla que almacenará datos cifrados**.
- Ninguna clave, contraseña SMTP o token debe mostrarse en logs ni respuestas de la API.

## Cifrado servidor (implementación a diseñar y validar)

- Usar primitivas auditadas como **libsodium de PHP** con `crypto_aead_xchacha20poly1305_ietf_*` si el hosting las ofrece; alternativa segura y bien revisada AES-256-GCM mediante biblioteca estándar. No diseñar criptografía propia.
- Clave aleatoria de 256 bits generada con un CSPRNG, guardada en secreto del servidor/KMS externo al repositorio y a `public_html`. Establecer permisos estrictos, recuperación segura, backup de clave separado, rotación y versión de clave.
- Para cada operación de guardado, usar **nonce fresco y único** junto con AEAD, y asociar metadatos de contexto autenticados (tipo de dato, ID de informe y versión de formato cuando corresponda).
- Persistir envoltorio versionado (`key_id`, `nonce`, `ciphertext`, `version`) y nunca una copia adicional en claro del mismo JSON técnico.
- En rutas de lectura, consultar el informe, **comprobar permisos primero**, descifrar después, verificar integridad y entregar solo campos autorizados. No registrar el texto descifrado en errores.
- Enviar solo los datos necesarios al documento final o al módulo de correos autorizado.
- **Compatibilidad de firma/integridad:** actualmente el hash SHA-256 se calcula sobre el JSON original. Los cambios de nonce de cifrado no deben invalidar la firma visual ni el hash del contenido lógico. Definir un formato canónico estable antes de migrar.
- **Migración sin pérdida de datos:** copia cifrada de seguridad, inventario de tablas/columnas reales y tamaño de campo, pruebas de lectura de datos históricos, proceso reversible y verificación de conteos/checksums, sin sobreescribir registros legados a ciegas.
- Si la clave no existe, es incorrecta o el ciphertext falla autenticación, **fallar de manera segura** y no guardar silenciosamente en texto claro.

## Cifrado local/offline

- Web Crypto `SubtleCrypto` con **AES-GCM** y un IV nuevo por guardado, con protección frente a manipulación. Los datos de `drafts`, `pending`, `history` y `reports` deben cifrarse *antes* de `storagePut`.
- **La gestión de claves es la parte más difícil**: guardar una clave legible junto al borrador en IndexedDB no garantiza defensa frente a una persona con acceso al navegador. Definir una clave derivada de secreto de desbloqueo del usuario o un esquema recuperable adecuado, con expiración/bloqueo y manejo de contraseñas. Nunca usar la contraseña del usuario en texto claro persistido en el navegador.
- Mantener la capacidad offline solamente si las pruebas de cifrado, recuperación de sesión, sincronización y pérdida de conectividad son exitosas. **Alternativa temporal segura: desactivar el almacenamiento offline de datos sensibles hasta tener cifrado local fiable**, y avisar al usuario antes de hacerlo para no perder borradores existentes.
- No borrar ni migrar borradores no cifrados sin exportación o recuperación autorizada y pruebas de integridad.
- Limpiar copia de datos descifrados en memoria o vistas al cerrar sesión cuando sea práctico, y prevenir cache de respuestas privadas por service worker o intermediarios.

## Correos y documentos finales (pendientes)

- Usuario autorizado define destinatario perteneciente a empresa/obra correctas y confirma envío; no permitir envíos basados solo en un texto libre del formulario.
- SMTP autenticado con **TLS**, credenciales secretas en servidor y límites de envío; registrar quién envió, cuándo y a quién, fallos y reintentos sin duplicados.
- Considerar enlace autenticado con caducidad al portal del cliente cuando el PDF contiene información sensible, en vez de enviar adjuntos expuestos. TLS de transporte **no cifra por sí mismo** el contenido de un correo de extremo a extremo.
- PDF final reproduce plantilla aprobada y firma manuscrita o método que el laboratorio defina; no afirmar que una imagen pegada sea firma electrónica avanzada.
- No guardar PDF descifrado en carpetas públicas accesibles por URL directa; descarga con verificación de acceso y auditoría.

## Pruebas de aceptación necesarias

1. Guardar informe nuevo y actualizarlo: no aparecen valores técnicos claros dentro de la columna de datos ni en dumps.
2. Leer y verificar datos legítimos: idénticos a lo ingresado, sin pérdida de precisión ni formato.
3. Dos guardados con el mismo contenido producen ciphertext distinto (nonce nuevo); las claves permanecen secretas.
4. Manipular el ciphertext o usar clave equivocada produce un error seguro, no datos corruptos ni fallo silencioso.
5. Usuario sin autorización no obtiene resultados ni PDFs aunque conozca un ID.
6. Borradores, pendientes e historial local quedan cifrados y se recuperan correctamente en modalidad offline habilitada.
7. La migración de datos antiguos se valida sobre una copia de respaldo, incluidos informes aprobados/firma/hash.
8. Restablecer el sistema desde backup **y clave respaldada separadamente** reproduce registros íntegros.
9. Una falla de SMTP no duplica envíos, ni marca un informe como entregado sin confirmación.
10. Se puede revocar acceso a un usuario sin descifrar información para él.

## Referencias de diseño

- OWASP Cryptographic Storage Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html
- OWASP Key Management Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Key_Management_Cheat_Sheet.html
- MDN Web Crypto API: https://developer.mozilla.org/en-US/docs/Web/API/Web_Crypto_API

**No aplicar esta propuesta a producción sin verificar extensiones de PHP en cPanel, respaldar datos, configurar claves privadas y probar migraciones.**
