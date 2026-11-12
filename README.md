# Controlab Documents — Prototipo Slim 4

**Estado:** demo de evaluación, **NO es un sistema de informes oficiales**. No usar información real de clientes ni muestras sin autorización y revisión técnica.

## Alcance

- Una **orden de trabajo (OT)** contiene uno o más **ensayos**.
- Cada ensayo de densidad usa tantas hojas A4 como requiera: **1–6 controles = hoja 1; 7–12 = hoja 2; 13–18 = hoja 3**, etc. Se repiten cliente, obra, OT y laboratorista sin copiar/pegar.
- Se pueden añadir otros ensayos con **plantilla genérica de demostración**, no validada.
- La vista previa permite **imprimir/guardar un PDF conjunto** (no une PDF existentes ni firma documentos).
- La interfaz móvil guarda localmente mientras se edita y permite **guardar/cargar una OT en MySQL usando API Slim 4**. El guardado remoto es explícito, no automático.
- Versionado optimista por `revision`: los cambios de un segundo usuario no sobreescriben silenciosamente los anteriores; un conflicto devuelve HTTP **409**.

## Tecnología

- PHP **8.1+**, [Slim Framework 4](https://www.slimframework.com/docs/v4/), Slim PSR-7 y Composer.
- MySQL/MariaDB con PDO y consultas parametrizadas.
- HTML, CSS y JavaScript sin dependencias externas para la vista previa y `window.print()`.

## Instalar en hosting de pruebas

1. Crea un subdominio HTTPS de pruebas. **DocumentRoot debe apuntar a `controlab-documents/public`**, dejando `src`, `private`, `database` y `vendor` fuera de la raíz web. **No subas el proyecto completo a `public_html`** si tu hosting no permite configurar el DocumentRoot apropiado.
2. En la raíz del proyecto ejecuta `composer install --no-dev --optimize-autoloader` (si el hosting no tiene Composer, prepara la carpeta `vendor` localmente con la versión correcta de PHP y súbela por un canal seguro).
3. Crea base de datos y usuario con permisos restringidos. Importa `database/schema.sql` en MySQL.
4. Copia `private/config.example.php` a `private/config.php` y configura el acceso a MySQL. Nunca publiques ni subas a GitHub el archivo real de credenciales.
5. Desde **línea de comandos**: `php private/create_admin.php operador`, con contraseña de 12+ caracteres. **Nunca expongas este script por HTTP**.
6. Comprueba `GET https://tu-subdominio/api/health` (debe responder `{"ok":true,"framework":"Slim 4"}`); entra en `/` para usar el formulario y luego en **Guardar en hosting** para iniciar sesión.
7. Ingresa o corrige una OT, pulsa **Guardar OT actual en servidor**. Abre el sistema desde otro equipo autenticado y utiliza **Cargar a este dispositivo**.

Para desarrollo local una vez instalado Composer: `php -S 127.0.0.1:8080 -t public public/router.php`. Entra en `http://127.0.0.1:8080`.

> La pantalla local sigue funcionando sin MySQL. Sin `vendor`, la API Slim no estará disponible. El archivo `private/config.php` y las credenciales **no** se incluyen en el repositorio.

## Rutas Slim 4 (JSON, mismo origen)

| Método | Ruta | Acción |
| --- | --- | --- |
| GET | `/api/health` | Estado de API, sin exponer detalles privados |
| GET | `/api/session` | Estado de sesión y token CSRF |
| POST | `/api/login` | Usuario y contraseña |
| POST | `/api/logout` | Cerrar sesión |
| GET | `/api/orders` | Listar órdenes autenticadas |
| GET | `/api/orders/{id}` | Cargar orden con revisión |
| POST | `/api/orders` | Crear orden con `expected_revision:0` |
| PUT | `/api/orders/{id}` | Actualizar con `expected_revision:n` |

Una operación de escritura requiere cookie de sesión y header `X-CSRF-Token` devuelto en `/api/session` o `/api/login`. El contenido es JSON; `POST /api/orders` y `PUT /api/orders/{id}` esperan:

```json
{
  "expected_revision": 0,
  "order": {"id":"demo-ot-001", "ot":"OT-DEMO-001", "clientName":"Cliente ficticio", "tests": []}
}
```

El backend guarda la **OT completa como JSON** en `orders.data_json`. No crea PDF en el servidor: el PDF se genera desde el navegador con el formato demostrativo.

## Limitaciones y controles previos a producción

- No está integrado con el portal actual, FTP, archivos compartidos ni firma electrónica. La relación OT–informes se administra solo dentro de esta demo.
- La aplicación **no certifica** resultados. La fórmula orientativa de densidad seca/compactación y sus redondeos, los formatos, la paginación A4 y la impresión deben ser validados por el responsable técnico.
- El cambio de datos de un ensayo revisado devuelve su estado local a borrador, **pero no existe auditoría de cambios ni versionado de informes firmados**. Los estados no representan una firma real.
- Antes de usar datos reales: políticas de usuarios/permisos por OT, auditoría, copias de seguridad, HTTPS forzado, antibruteforce/rate limiting, recuperación de contraseña, registro de revisiones/aprobaciones y validación normativa.
- El modelo comparte la lista de órdenes entre todos los usuarios autenticados. No está preparado para acceso de clientes.

## Estructura

```text
public/index.html       Formulario móvil y generador PDF
public/index.php        Entrada Slim 4 y rutas REST
public/.htaccess        Reescritura a Slim (Apache)
public/router.php       Router opcional para servidor PHP local
src/Support.php         PDO, validación, respuestas y control de versiones
private/config.example.php  Ejemplo de configuración privada
private/create_admin.php    Creación de usuario por CLI
database/schema.sql     Esquema MySQL
composer.json           Dependencias Slim 4
```
