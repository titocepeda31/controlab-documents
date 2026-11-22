# Controlab — Usuarios, responsabilidades y acceso por empresa

> **Rama de desarrollo:** `feature/usuarios-acceso-empresas`.
> NO desplegar hasta completar migración y pruebas. No se ha alterado la base de datos ni el hosting mediante este repositorio.

## Caso de uso Forestal 3M

- **Administrador:** registra empresas, crea usuarios y asigna qué empresa puede consultar cada informe.
- **Responsable técnico (ej. Roberto):** prepara informes, solicita revisión, revisa y firma registros provisionales. Puede firmar si está habilitado como revisor.
- **Digitalizador:** ingresa resultados, corrige sus registros devueltos, solicita revisión. No puede aprobar.
- **Cliente Forestal 3M:** solo puede consultar informes **aprobados y expresamente asignados** a Forestal 3M. No puede editar, revisar, firmar, duplicar ni acceder a empresas de otros clientes.

**Importante:** ninguna relación de acceso se deduce de texto escrito en `contratista` o `mandante`. Se asigna `empresa_id` en `informe_destinatarios`. Los informes históricos no se comparten automáticamente: solo el administrador decide cuáles serán visibles.

## Instalación (cuando se autorice despliegue)

1. Respaldar la base de datos y las carpetas `/home/maderas7/demo-informes/` y `/home/maderas7/public_html/demo/`.
2. En phpMyAdmin de `maderas7_informes`, ejecutar `mvc/database/crear_usuarios_empresas.sql`, que agrega **dos tablas** sin alterar las anteriores: `usuario_accesos` e `informe_destinatarios`.
3. Asignar el perfil de administrador a la cuenta existente. Sustituir el correo **por el correo verdadero de la cuenta administradora**:

   ```sql
   INSERT INTO usuario_accesos (usuario_id,nombre,rol,empresa_id)
   SELECT id,'Administrador Controlab','administrador',NULL
   FROM usuarios WHERE email='CORREO_REAL_DEL_ADMINISTRADOR' AND activo=1
   ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),rol='administrador',empresa_id=NULL;
   ```

4. Confirmar que el administrador quedó registrado:

   ```sql
   SELECT u.id,u.email,a.rol
   FROM usuarios u JOIN usuario_accesos a ON a.usuario_id=u.id
   WHERE u.activo=1 AND a.rol='administrador';
   ```

5. Recién después de habilitar un administrador, realizar pruebas de despliegue en entorno de ensayo. El script `cpanel_mvc_deploy.sh` revisa la migración y que haya al menos un administrador activo; si no, **no reemplaza archivos**.
6. Cerrar sesión y abrir el formulario nuevo. El administrador verá **Usuarios y empresas**, desde donde crea Forestal 3M y registra las cuentas de Roberto, el digitalizador y el acceso de cliente.
7. En **Historial**, un administrador podrá pulsar **Compartir con empresa**, seleccionar Forestal 3M y guardar. **El cliente solo verá ese informe una vez que esté aprobado.**
8. Abrir una sesión de cliente y comprobar que solo se ve el informe asignado, que no aparecen formularios de edición ni cuentas ajenas, y que las rutas de API a informes de otra empresa devuelven 404/403.

## Seguridad

- Las contraseñas temporales se generan de forma aleatoria al crear el usuario; solo se muestran una vez. El usuario debe utilizar **Mi clave** para cambiarlas.
- Ninguna contraseña real debe subirse al repositorio público.
- **Roles/permisos se validan en el backend**, no solo mediante botones ocultos. Clientes sin empresa no obtienen sesión válida.
- La firma es **visual de piloto**, no firma electrónica avanzada. La impresión sigue siendo un **PDF de prueba**, no el informe técnico oficial.
- No se ha implementado aún el correo masivo: el digitalizador podrá preparar el registro, pero su envío seguirá pendiente hasta concluir el PDF oficial y el módulo de correo.
- Las cuentas cliente no tienen trabajo sin conexión ni descarga de historial para caché local.
- Los usuarios existentes sin perfil en `usuario_accesos` quedarán bloqueados hasta que el administrador les asigne uno.

## Lista de comprobación mínima

- Cuenta admin puede crear empresa y usuario sin usar datos reales de prueba.
- Cuenta `digitalizador` puede crear y guardar, pero no revisar ni firmar.
- Cuenta `responsable` puede solicitar revisión y aprobar con firma visual.
- Cuenta `cliente` ve exclusivamente aprobados asignados a su empresa.
- Cambiar el ID en una URL / API no permite ver informes de otra empresa.
- Desactivar una cuenta impide acceso incluso si la sesión PHP sigue abierta.
- Cambiar perfil de interno a cliente elimina el historial local de lectura y bloquea las operaciones internas.
- Cambio de contraseña: la anterior deja de funcionar, la nueva sí.
- Impresión del informe de prueba muestra empresa destinataria real; ya no menciona por defecto otra forestal.
