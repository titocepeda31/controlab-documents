# Controlab — Planificador de órdenes de trabajo (piloto Forestal 3M)

## Qué incluye esta primera versión
- Registro de OT: número propio o correlativo automático `OT-AAAA-ID`, cliente/contratista, obra, actividad, predio/dirección de destino, comuna, inicio/fin, prioridad, estado e instrucciones.
- Asignación de **varios laboratoristas y digitadores** a una OT, utilizando los perfiles activos existentes.
- **Calendario mensual** con fecha seleccionable, **agenda** y vista **Equipo** para saber a qué trabajo y ubicación quedó asignada cada persona.
- Filtro por trabajador y estado, con visualización en móvil.
- Al consultar una OT, opción **Buscar ubicación en Maps** (abre una búsqueda externa por el texto proporcionado; no implica coordenadas verificadas).
- Evita la **superposición de horarios** para una misma persona si ambas OT están planificadas o en curso. Se permite OT contiguas en las que una termina exactamente cuando empieza otra.
- Administración simultánea más segura: bloqueo de trabajadores en MySQL y transacciones `READ COMMITTED` para impedir dobles asignaciones; campo `version` para impedir sobrescribir otra edición.
- Bitácora mínima de creación y actualización en `ot_eventos`. No borra OTs: se marcan `cancelada` o `finalizada`.

## Roles
| Rol | Calendario | Consultar destino | Crear / editar OT | Seleccionar personal |
|---|---|---|---|---|
| Administrador | Todas las OT | Sí | Sí | Sí |
| Administrativo | Todas las OT | Sí | Sí | Sí |
| Laboratorista | Solo sus OT | Sí | No | No |
| Digitador | Solo sus OT | Sí | No | No |

Los usuarios solo ven sus propias asignaciones (y el nombre de compañeros de la misma OT), no la lista global de la empresa. La API aplica permisos con sesión/cifrado de cookie y CSRF, no solo mediante ocultación de botones.

## Instalación desde cPanel (sin SSH)

**Antes de desplegar:**
1. En cPanel → phpMyAdmin → la base de datos usada por Controlab (`maderas7_informes` en esta instalación) → SQL.
2. Ejecutar **completo y una sola vez** el script `mvc/database/crear_planificador_ot.sql`, que crea `ot_ordenes`, `ot_asignaciones` y `ot_eventos`. No modifica ni elimina informes o usuarios previos.
3. Comprobar con SQL:
```sql
SHOW TABLES LIKE 'ot_%';
```
Deben aparecer las tres tablas.
4. En Git Version Control → `controlab-documents` → Update from Remote → **Deploy HEAD Commit**. El script comprueba las tres tablas antes de copiar archivos y publica `planificador.html` con permisos `644`.
5. Reabrir Controlab e iniciar sesión si es necesario. Abrir ☰ → **Planificador OT** o `https://maderasyferreteriasmuena.cl/demo/planificador.html`.
6. Ingresar como administrador/administrativo, pulsar **Nueva orden de trabajo**, completar destino y horarios, seleccionar personal, guardar. Comprobar calendario, agenda y Equipo con otra cuenta laboratorista.

Si cPanel indica fallo en Deploy, consultar el mensaje exacto; no borrar datos ni reemplazar el `config.php` privado. Si solo el HTML da 404, revisar que `public_html/demo/planificador.html` exista y tenga permisos 644.

## Reglas / alcance actual
- Los horarios son **locales de Chile** (`America/Santiago`), almacenados como `DATETIME`; no hay sincronización con Google Calendar u Outlook.
- Se pueden programar OTs de hasta 31 días; el calendario consulta bloques de hasta 100 días y muestra hasta 500 resultados por consulta.
- La prevención de choques solo cubre **superposición del horario**. No calcula duración de traslado, tiempos de desplazamiento, disponibilidad de vehículos ni equipos de ensayo.
- La dirección es un dato descriptivo: la opción Maps realiza una búsqueda, no un geocodificado confirmado ni GPS del trabajador.
- El planificador es **en línea**. No guarda datos de cuadrillas ni su ubicación en IndexedDB para trabajo sin conexión. El formulario de informes mantiene su funcionamiento offline.
- La versión actual asigna **personas directamente** a cada OT; todavía no dispone de plantillas de cuadrillas permanentes, traspaso de OT a informes, notificaciones, integración de vehículos, rutas ni correo.
- No es seguimiento en tiempo real: «dónde queda» significa **destino planificado**, no ubicación GPS ni hora real de llegada.
- Para que un trabajador vea su OT, debe tener usuario activo y estar asignado; si no aparece en el selector, asignar primero un perfil `laboratorista` o `digitador` en Usuarios.
- Las OT antiguas en estado finalizada/cancelada permanecen en el historial, pero no bloquean nuevas asignaciones.
