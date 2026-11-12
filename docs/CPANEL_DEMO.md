# Controlab Documents: demo en cPanel (sin SSH)

**Estado:** preparada para una publicación manual. La URL no existe hasta que el titular del hosting autorice el uso, clone el repositorio y ejecute **Deploy HEAD Commit**. Esta operación publica solo HTML ficticio; no instala Slim 4, Composer ni MySQL.

## Seguridad y permisos

El hosting conocido por Tito POS corresponde a otra actividad/empresa. **No lo uses para documentos de clientes o ensayos reales del laboratorio sin autorización de ambas partes.** Para un piloto real, contratar o configurar un alojamiento y dominio autorizados, además de los controles de seguridad.

## Evidencia en Tito POS

En el repo `titocepeda31/tito-pos-react` existe `.cpanel.yml` y un script `scripts/cpanel_deploy_api.sh`, que cPanel usa con la opción **Deploy HEAD Commit**. Ese script exige `vendor/autoload.php` existente: **no instala Composer**. El repo menciona PHP 8.3, MySQL y Slim 4, pero esto no comprueba todavía la disponibilidad actual del servidor.

## Publicación exclusiva de la demo

1. Confirma autorización del titular del hosting y el dominio de pruebas. Usa únicamente el conjunto ficticio incluido en el HTML.
2. En **cPanel > Git Version Control**, clona el repositorio privado `titocepeda31/controlab-documents` en `/home/maderas7/repositories/controlab-documents`. Si GitHub solicita autenticación, configura una **deploy key de solo lectura**. No almacenes claves privadas en el repositorio.
3. Si cPanel asigna una ruta diferente, actualiza primero la ruta absoluta de `.cpanel.yml`. No ejecutes el despliegue con una ruta que apunta a otro proyecto.
4. Si hay terminal o una función para ejecutar `.sh`, puedes realizar una revisión no destructiva con `bash scripts/cpanel_demo_deploy.sh --check`.
5. En **Git Version Control > Manage > Pull or Deploy**, utiliza **Deploy HEAD Commit**. La tarea de `.cpanel.yml` llamará al script `scripts/cpanel_demo_deploy.sh --deploy`.
6. El script publicará solo `public/index.html` en `/home/maderas7/public_html/controlab-demo/index.html`. La dirección esperada será `https://DOMINIO-AUTORIZADO/controlab-demo/`. Debes disponer previamente de DNS y HTTPS funcionando; el script no los instala.

### Precauciones incorporadas

- El script bloquea destinos que ya contengan archivos ajenos.
- Copia exclusivamente el HTML de la demo, con información ficticia.
- Respalda cualquier versión anterior de la demo bajo `$HOME/controlab-documents-demo-backups` (carpeta privada).
- **No toca Tito POS, otras aplicaciones, FTP, MySQL, Composer ni el portal actual.**
- Como es un sitio accesible por URL, cualquiera con esa URL podría abrirlo. No usar datos reales del laboratorio.
- La sección **Guardar en hosting** permanece desactivada funcionalmente: no existe aún la API `/api/*` de este proyecto en ese dominio.

## Próxima fase: instalación de API Slim 4

Este paso es **independiente** del despliegue de la demo:

1. Aprobar un subdominio HTTPS y DocumentRoot que apunte a `controlab-documents/public`; nunca exponer `private/`, `database/` ni `vendor/` en `public_html`.
2. Crear una base MySQL y un usuario **separados** del inventario Tito POS.
3. Instalar Composer y las dependencias con PHP CLI desde un entorno autorizado, o preparar `vendor/` en otro equipo compatible y subirlo de forma segura. No se requiere SSH interactivo si cPanel permite ejecutarlo por un mecanismo autorizado. No ejecutar Composer mediante un PHP público.
4. Configurar las credenciales únicamente en `private/config.php`, ignorado por Git.
5. Crear usuario de prueba mediante `private/create_admin.php` **solo por CLI**.
6. Verificar `/api/health`, login, sesiones, CSRF, guardar/cargar OT y conflictos de edición con datos ficticios.

**Antes de producción:** confirmar firma/revisión responsable, auditoría, control de usuarios, copias de seguridad, privacidad y validación técnica del documento oficial.
