# Controlab — Administrador de empresas/obras y mapa OpenStreetMap

## Funcionalidad y ubicación
- Menú ☰ en `formulario.html` → **Empresas · Obras · Personal** para administrador o administrativo. El enlace directo es `/demo/catalogos.html`.
- Alta de constructoras, obras (dirección y comuna) y personal sin cuenta SOLO en ese administrador. No se crean desde el planificador de OT.
- En `planificador.html`, dos campos de búsqueda con autocompletado **local** contra catálogos de la base: **empresa** → **obra**. Una vez seleccionada la obra, cliente, dirección y comuna se completan automáticamente. El servidor valida el `obra_id` y evita confiar en campos manipulados.
- En el administrador de obras, **Editar y ubicar** muestra mapa Leaflet/OpenStreetMap. Pulsar **Buscar dirección** consulta Nominatim de forma puntual (NO busca mientras se escribe). Seleccionar una sugerencia o pulsar/arrastrar el marcador fija coordenadas y **Guardar ubicación** las almacena en `obra_ubicaciones`.
- Al consultar una OT vinculada, aparece el destino en un mapa OpenStreetMap y botones para abrir el destino y **Cómo llegar desde mi ubicación**. Esta última acción pide permiso GPS solo cuando el usuario pulsa el botón, y delega el cálculo de ruta de automóvil a OpenStreetMap/OSRM. No rastrea empleados ni almacena su GPS.

## Instalación en cPanel
1. Confirmar previamente que está instalado `mvc/database/crear_obras_y_personal_terreno.sql`.
2. Ejecutar en **phpMyAdmin**, base `maderas7_informes`, el contenido íntegro de `mvc/database/crear_ubicaciones_osm.sql`. Crea únicamente `obra_ubicaciones`, `geo_busquedas_cache` y `geo_nominatim_limite`, e inicializa su limitador. No modifica datos existentes.
3. En cPanel Git Version Control → repositorio `controlab-documents` → **Update from Remote** → **Deploy HEAD Commit**. El despliegue se detiene si falta cualquiera de las tablas.
4. Cerrar y volver a abrir `/demo/formulario.html`, iniciar sesión, abrir menú ☰ → **Empresas · Obras · Personal**. Si no aparece, comprobar rol administrador/administrativo y despliegue exitoso; probar directamente `/demo/catalogos.html`.
5. Crear empresa, luego obra; abrir **Editar y ubicar**, buscar una dirección y confirmar manualmente marcador. Guardar ubicación. Ir al Planificador OT → Nueva OT → buscar empresa y obra.
6. Desde el detalle de OT, comprobar mapa y opción de ruta.

## Importante para mapa / límites
- **OpenStreetMap es la fuente de datos/cartografía, no un servicio gratuito ilimitado de rutas y geocodificación.**
- El piloto usa imágenes de `tile.openstreetmap.org` con atribución © OpenStreetMap contributors, sujeto a [política de teselas](https://operations.osmfoundation.org/policies/tiles/).
- Nominatim público se usa exclusivamente al pulsar Buscar dirección, mediante un **endpoint PHP autenticado**, con bloqueo global en MariaDB, límite mínimo 1,1 s entre llamadas y caché de 30 días; no hay autocompletado remoto ni búsquedas masivas. Consultar [política Nominatim](https://operations.osmfoundation.org/policies/nominatim/). Requiere que PHP cURL esté habilitado y permita conexión HTTPS saliente. A alto volumen deberá emplearse proveedor dedicado o instancia propia. Los resultados pueden ser imprecisos en caminos rurales; el usuario debe verificar/ajustar el punto.
- La ruta utiliza el enlace público de direcciones OSM y se abre fuera de Controlab. Requiere GPS del navegador autorizado por cada acción; no se calcula ruta/ETA en el servidor de Controlab ni se guarda el origen.
- Las coordenadas (latitud/longitud) de las obras se consideran **destino de obra**. No son ubicación en tiempo real de trabajadores. Para conocer dónde está una persona realmente, se necesita otro módulo de check-in o seguimiento con consentimiento y una política de privacidad.
- Si no se encuentra una dirección con Nominatim, es posible abrir el mapa y marcar manualmente. Si falla la carga de Leaflet/CDN, la funcionalidad de mapa no estará disponible hasta tener conexión; la administración de empresa/obra puede funcionar independientemente.
