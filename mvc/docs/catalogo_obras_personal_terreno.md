# Controlab — Catálogo de empresas, obras y personal de terreno

## Relación de datos
- Una **empresa constructora** es un registro de la tabla `empresas`, reutilizada de Controlab. Puede tener muchas **obras**, cada una con nombre, dirección/ubicación, comuna y estado activo.
- Una **orden de trabajo** puede estar vinculada a una obra de catálogo mediante `ot_obra_vinculos`. La OT sigue conservando su empresa, obra, dirección y comuna como **instantánea**, para que cambiar la dirección de una obra no reescriba históricos sin intervención.
- Un **trabajador de terreno** (`personal_terreno`) tiene nombre, función/cargo, observaciones opcionales y estado activo. No está en `usuarios`: NO tiene correo de acceso ni contraseña y no puede iniciar sesión.
- Una OT puede asignarse a `ot_personal_terreno` (conductores, muestreadores, ayudantes u otros sin cuenta) y, separadamente, a `ot_asignaciones` (laboratoristas/digitadores con cuenta). Ambos aparecen en el calendario y vista Equipo.
- Se evitan choques de horario para ambos conjuntos. Inactivar una persona no elimina su historial; evita nuevas asignaciones.
- No se vinculan automáticamente fichas de personal a cuentas, para no crear usuarios de manera implícita. Si una persona está en ambos catálogos, los administrativos deben evitar asignarla dos veces como si fueran personas distintas; la vinculación formal es una fase futura.

## Instalación desde cPanel / phpMyAdmin

**1. ANTES de actualizar desde remoto**, abrir cPanel → phpMyAdmin → base `maderas7_informes` → SQL. Ejecutar TODO el contenido de `mvc/database/crear_obras_y_personal_terreno.sql` que solo crea:

- `obras`
- `personal_terreno`
- `ot_obra_vinculos`
- `ot_personal_terreno`

No elimina ni modifica tablas existentes. Es repetible con `CREATE TABLE IF NOT EXISTS` y no asigna permisos de acceso.

**2. Verificar** con esta consulta (debe mostrar las cuatro tablas):

```sql
SHOW TABLES LIKE 'obras';
SHOW TABLES LIKE 'personal_terreno';
SHOW TABLES LIKE 'ot_obra_vinculos';
SHOW TABLES LIKE 'ot_personal_terreno';
```

**3. Actualizar** Git Version Control → `controlab-documents` → Update from Remote → Deploy HEAD Commit. El despliegue se detiene si falta alguna tabla, sin reemplazar archivos antes de pasar las verificaciones. Los archivos públicos se copian con permisos `644`.

**4. Probar en el navegador**:
- Iniciar sesión con cuenta `administrador` o `administrativo`.
- Planificador OT → **Empresas · Obras · Personal**, o `https://maderasyferreteriasmuena.cl/demo/catalogos.html`.
- Agregar obra para una empresa existente, con dirección y comuna propias.
- Agregar un conductor/muestreador sin correo ni contraseña; comprobar que NO aparece como cuenta en Administración de usuarios.
- Crear OT, seleccionar empresa → obra y comprobar que completa cliente, obra, dirección y comuna. Asignar trabajador de terreno.
- Ver la OT en calendario y en Equipo. Intentar agendar al mismo trabajador en el mismo horario debe devolver conflicto.

## Permisos y compatibilidad
- Sólo perfiles **administrador** y **administrativo** pueden crear/editar catálogos y administrar asignaciones de OT. La API también valida sesión y CSRF.
- Los trabajadores que **no tienen cuenta** no acceden al calendario; son datos de planificación visibles para quienes tengan permisos administrativos y para usuarios asignados a una OT.
- Laboratoristas y digitadores **con usuario** consultan sus propias OT. No se entrega automáticamente acceso a quienes figuran en `personal_terreno`.
- Las OT previas sin vínculo conservan todos sus campos originales. Pueden permanecer como registros anteriores o vincularse manualmente a una obra al editarlas.
- Los informes existentes **no se vinculan automáticamente a obras** todavía. Integrar `obra_id` y `ot_id` al flujo de informes se hará en otra etapa para evitar correspondencias incorrectas.
- No hay ubicación GPS: dirección y comuna son datos proporcionados por administración para planificar destinos.
- Los registros y cambios de catálogos requieren conexión. El ingreso de informes offline existente se mantiene sin cambios.
