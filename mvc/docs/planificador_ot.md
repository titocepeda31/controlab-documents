# Módulo propuesto: Planificador de órdenes de trabajo y salidas a terreno

Estado: **diseño funcional preliminar**, separado del piloto de informes de suelo de Forestal 3M. No implementar automatizaciones ni módulos técnicos que aún no han sido definidos con personal del laboratorio.

## Problema operativo
Hoy se reciben solicitudes por correo, se generan las órdenes de trabajo (OT) en Excel y luego se programan en papel los trabajadores, equipos y servicios en terreno. La información se repite y no hay un calendario central accesible. El piloto actual de Roberto/Forestal 3M es un sistema de informes; este módulo sería complementario.

## Entidades

1. **Empresa / cliente** (razón social, contacto, correo, estado): una empresa tiene muchas obras.
2. **Obra** (nombre, empresa_id, dirección/comuna/referencia de acceso, coordenadas si se proporcionan, responsable de contacto, teléfono): una obra puede generar muchas órdenes.
3. **Solicitud** (fecha de correo o solicitud, remitente, motivo, adjuntos, estado de recepción): etapa de pre-registro. Inicialmente creación manual y opcional adjuntar el correo o respaldo; integración de lectura automática de correos es futura y requiere autorización expresa y conexión segura. No atribuir un cliente automáticamente si no está confirmado.
4. **Orden de trabajo / OT** (número de OT actualmente creado y gestionado en Excel, obra_id, fecha requerida, prioridad, instrucciones internas, estado, persona creadora, origen de solicitud): una OT puede contener **varios servicios**. La futura aplicación debe permitir registrar las OT existentes respetando el número original y prevenir duplicados; no asumir que genera una nueva numeración sin validar el procedimiento.
5. **Tipo de servicio** configurable (nombre, categoría, duración estimada opcional, equipamiento típico). No fijar catálogo hasta confirmar nombres internos; ejemplos referenciales: densidad en terreno, extracción de testigos, calicatas, toma de muestras.
6. **Servicio de OT** (ot_id, tipo_servicio_id, cantidad de puntos/muestras si procede, indicaciones).
7. **Visita / salida a terreno** (ot_id, fecha, hora inicio y fin estimadas, lugar de encuentro, instrucciones de acceso, estado): una OT puede necesitar varias salidas.
8. **Asignación de personal** (visita_id, usuario_id, función asignada): una salida puede tener varias personas y una persona varias visitas en el tiempo.
9. **Equipos / recursos** (código, nombre, estado operativo, fecha de calibración/mantenimiento si aplica).
10. **Asignación de equipo** (visita_id, equipo_id): evitar doble reserva simultánea, alertar por equipo indisponible.
11. **Resultado o parte de terreno** (visita_id, fecha de ejecución, estado, observaciones técnicas y fotografías opcionales; idealmente **datos escritos en campos**, no solo fotos de manuscritos).
12. **Informes** (vinculación a obra y, cuando proceda, a OT y servicio). Cada obra mantiene **su propio correlativo de informes**, separado del número de OT. Un mismo número de informe puede existir en obras distintas, pero nunca repetirse dentro de una misma obra. El formato específico depende de cada tipo de ensayo. No cambiar el PDF piloto sin recibir la plantilla original.

## Dos numeraciones diferentes: OT e informe por obra

**Lo confirmado en la conversación:** las OT se crean actualmente en Excel; cada obra tiene su propia secuencia de números de informe. La empresa constructora y el laboratorio necesitan saber qué números faltan dentro de una obra. La regla de reinicio por año, qué persona asigna el siguiente número, si puede reservarse/anularse y la relación exacta OT-informe todavía deben confirmarse.

- `ot.numero_ot`: identificador de orden de trabajo conservado desde Excel, con restricción de unicidad según la regla vigente del laboratorio (alcance por definir). **No es** el correlativo del informe.
- `informe.obra_id`: referencia obligatoria a una obra cuando se implemente el nuevo módulo.
- `informe.correlativo_obra`: número asignado dentro de esa obra; imponer unicidad **(`obra_id`, `correlativo_obra`)** mediante índice en MySQL. La clave de secuencia puede requerir también año/serie si se confirma que se reinicia.
- `informe.ot_id`: vínculo a su OT de origen si corresponde, sin hacer que el correlativo dependa de la OT. La cardinalidad exacta (una OT puede producir varios informes) es una hipótesis que debe verificarse.
- Estado de registro del informe: `borrador`, `en elaboración`, `listo`, `enviado`, `anulado` o nomenclatura interna definitiva. Separar el **estado del informe** del **estado de envío** y de la **existencia del número**.
- Reserva del correlativo con transacción de MySQL o mecanismo equivalente, para evitar que dos personas asignen simultáneamente el mismo número. Nunca utilizar simplemente `MAX(correlativo)+1` sin protección frente a concurrencia.

### Ejemplo ilustrativo

Obra «Camino Norte»: informes N° 1, 2, 4, 7, 8, ..., 20. La pantalla de control mostraría **faltantes por verificar: 3, 5 y 6**. Pero un número ausente **no significa automáticamente** que el ensayo esté pendiente: podría no haberse generado, estar reservado, anulado, aún sin digitalizar o extraviado. Hay que reconciliar cada falta con el Excel o registro histórico.

La obra «Camino Sur» puede tener su propio informe N° 3, sin chocar con «Camino Norte» N° 3.

### Pantalla «Control de correlativos»

Para cada empresa y obra:
- Número máximo de informe conocido y registros existentes.
- Correlativos faltantes, reservados, anulados, pendientes de confección, listos, enviados (si hay evidencia del envío).
- Acceso directo desde un número a su ficha y a la OT correspondiente cuando exista.
- Filtros por estado, tipo de ensayo y fecha.
- Posibilidad de importar los números históricos de Excel y **revisar conflictos o duplicados antes de crear registros**; nunca renumerar archivos antiguos automáticamente.

### Despliegue por etapas

1. Registrar empresa → obras. Conservar los Excel actuales como fuente de referencia al inicio.
2. Importar o registrar OT respetando su número ya asignado.
3. Añadir obra y correlativo por obra a los informes nuevos **sin reemplazar el identificador interno de MySQL existente**.
4. Conciliar historial y detectar huecos, distinguiendo «número no encontrado» de «informe pendiente».
5. Más adelante, crear OT dentro del sistema y reemplazar el Excel solo cuando las personas encargadas aprueben el proceso.

## Hallazgo operativo: bloques de correlativos y OT (Órdenes de Trabajo) impresas

**Flujo actual confirmado por el usuario (2026-11-22):**
- Se consulta el Excel por **empresa** y se filtra por **obra**.
- Se agregan correlativos manualmente, uno tras otro (1, 2, 3, ...). Hay pedidos de grandes cantidades, por ejemplo **50 correlativos**.
- **Cada correlativo queda asociado a documentación física, incluida una OT (Orden de Trabajo) impresa**, según la descripción actual del proceso. Hay que verificar con una muestra real si se emite exactamente una OT por correlativo o si una OT puede respaldar varios informes.
- Deben esperar que otra persona desocupe el Excel antes de numerar; si trabajan simultáneamente **se pisan los números**.
- **Aclaración explícita del usuario:** la sigla correcta es **OT = Orden de Trabajo**. «OD» fue una transcripción equivocada; no usarla.
- Se mantienen **dos numeraciones distintas**: número de OT (orden de trabajo, actualmente creada en Excel) y número correlativo del informe, propio de cada obra. Conservar la relación entre ambas sin asumir aún si es 1:1 o 1:N.

**Requerimiento propuesto, por confirmar antes de implementar:**
1. Seleccionar empresa y obra existentes; ver último correlativo asignado, rangos reservados y disponibilidad.
2. Pedir la **cantidad** (por ejemplo 50). Una **transacción MySQL** con bloqueo de la secuencia de ESA obra asigna todo el rango en una operación indivisible; crear registros para todos los números y confirmar juntos.
3. Proteger con **índice único por obra y número** (y año/serie solo si se confirma reinicio). Evitar carrera simultánea mediante fila de secuencia bloqueada `SELECT ... FOR UPDATE`, transacción, validación del contador y rollback íntegro si ocurre un error. **No calcular solamente MAX()+1** en paralelo. Otros usuarios pueden trabajar al mismo tiempo sin reservar el mismo número. Las obras distintas mantienen contadores independientes.
4. Registrar auditoría del lote: quién solicitó, cuándo, empresa, obra, correlativo inicial/final, número de documentos solicitados, emisión/reimpresiones y motivo de anulaciones.
5. Generar las **OT con el formato que se imprime actualmente**, respetando la relación real entre OT y correlativos que se confirme al revisar la plantilla y el Excel. Permitir impresión por lote cuando corresponda, sin eliminar el respaldo físico requerido.
6. Etapas sugeridas del correlativo: **reservado/asignado**, **OT impresa**, **utilizado**, **anulado**; el estado de **informe listo** y **enviado** es independiente. Una reimpresión debe conservar el número y registrar la acción; nunca crear correlativo nuevo accidentalmente.
7. Permitir control por obra: total asignado, lote y números específicos, impresos, utilizados, anulados, pendientes de completar, listos y enviados; marcar faltantes **por investigar**, sin equipararlos automáticamente a informes no realizados.
8. Migrar desde el Excel actual con reconciliación: conservar numeraciones ya existentes y evitar reusar números históricos o reservados en papel.

**Diseño de UI sugerido**: Empresa → Obra → «Generar correlativos» → Cantidad → Previsualizar rango → Confirmar reserva → «Imprimir OT del lote» → Historial y seguimiento; todo en páginas, sin ventanas modales.

**Preguntas a verificar después con muestras reales:** relación exacta entre OT y correlativos de informe, cuándo se considera oficialmente consumido el correlativo, si la numeración se reinicia anualmente, quién tiene permiso para reservar lotes, cómo se controlan OT impresas no utilizadas, y si se necesita imprimir una OT individual por correlativo o un formato compuesto.

## Navegación (sin modales)
- **Empresas:** CRUD y obras asociadas a cada empresa.
- **Obras:** ficha, listado de OT y tablero de correlativos de informes de esa obra, dirección verificable.
- **Órdenes de trabajo:** crear OT, asociar uno o varios tipos de servicio; recepción, programación, seguimiento.
- **Calendario:** vistas día, semana y mes, con filtros por laboratorista, obra, equipo y estado; alerta de conflictos de horario y recursos.
- **Mi agenda:** pantalla simplificada para cada laboratorista con OT, fecha, hora, ubicación, tipo de servicio y compañeros/equipos asignados. **No mostrar cliente ni datos comerciales** si su trabajo no los requiere.
- **Terreno:** marcar inicio y cierre de visita, capturar lecturas/datos e incidencias; fotos solo de apoyo y según autorización.
- **Informes y entregas:** identificar cada informe por **obra + correlativo**, conectarlo al trabajo de la OT y dejar traza de quién generó y envió el documento.
- **Control de correlativos:** asignar bloques atómicamente por empresa/obra, registrar la OT impresa relacionada con cada número según la correspondencia real, controlar reimpresiones y señalar saltos numéricos y estados por obra.

## Flujo
Correo recibido -> recepción y validación manual -> Empresa y obra existentes o nuevas -> OT creada en Excel (o registrada/importada) con servicio(s) -> planificar visita(s) y asignar personas/equipos -> equipo visualiza 'Mi agenda' -> ejecutar en terreno -> registrar datos -> elaboración/validación de informe según tipo -> entrega al cliente autorizado.

## Estados iniciales sugeridos
- **OT:** recibida, por programar, programada, en ejecución, terminada, cerrada, anulada.
- **Visita:** pendiente, confirmada, en terreno, finalizada, reprogramada, cancelada.
- **Entrega de informe:** pendiente, listo para envío, enviado, entregado; sin inventar estado 'entregado' hasta que exista evidencia.

## Permisos
- Administración/coordinación puede registrar clientes, obras, OT, programación y asignaciones.
- Laboratoristas solo ven sus asignaciones, datos operacionales para llegar a la obra y servicios a efectuar. Acceso técnico no implica acceso a datos comerciales del cliente.
- Roberto, en piloto Forestal 3M, registra resultados en el formulario de suelo. Los demás ensayos no se implementan hasta obtener formatos y requisitos.
- Digitador de envíos podrá entregar informes aprobados; no alterar resultados de ensayo.
- Cliente, si se habilita portal, ve solo informes autorizados de su empresa; no las OT de otros clientes.
- Todas las restricciones deben aplicarse **en backend y API**, no solo ocultando campos en HTML.

## MVP sugerido para validar en terreno
1. Registrar empresa y obra, incluyendo dirección completa o referencia de acceso.
2. Registrar una OT y uno o varios servicios de un catálogo editable.
3. Programar fecha/hora y asignar laboratoristas; marcar recursos de forma simple.
4. Ver agenda semanal y agenda individual; evitar solapamientos.
5. Marcar la visita como terminada; dejar nota de resultados y vínculo eventual a informe.
6. Adjuntar correo/solicitud original cuando exista, sin intentar leer automáticamente buzones al inicio.

## Decisiones pendientes de conocer
- ¿Quién asigna el número de OT en Excel, cómo se numeran las OT y cómo se exportarán o importarán sin duplicar?
- ¿Quién asigna el correlativo de informe dentro de cada obra, se reinicia por año, y cómo se reserva/anula un número?
- ¿Una OT puede generar varios informes, o un informe relacionarse con varias OT?
- ¿Quién recibe correo y decide las fechas o asigna personal?
- ¿Cuándo necesita una OT más de una visita o más de un equipo?
- Catálogo real de servicios, nombres técnicos internos y requerimientos de equipos.
- Si la agenda debe informar al trabajador por correo/WhatsApp (posterior).
- Dirección exacta, puntos de muestreo y posibilidad de cambiar la programación.
- Qué datos técnicos debe llenar el laboratorista para cada ensayo, y cómo se vinculan con el informe correspondiente.

## Riesgos y diseño de seguridad
- No inferir direcciones, coordenadas, rutas ni tiempos de viaje desde nombres ambiguos de obras.
- No programar o asignar automáticamente desde un correo sin confirmación humana.
- No mezclar acceso a datos comerciales del cliente con las tareas de campo.
- Registrar bitácora de cambios de asignación, fecha y recurso.
- La función de agenda no reemplaza controles de calidad/calibración o aprobación de informes.
- No mezclar este desarrollo con el despliegue actual de formularios de Forestal 3M.

## Terminología
Los términos «densidad en terreno», «extracción de testigos» y «calicatas» figuran en catálogos públicos de laboratorios chilenos (p. ej. LabMaule y Laboratorio INSITU); son **ejemplos iniciales**, no el catálogo verificado del usuario. Su término recordado como 'canicama' podría referirse a 'calicata', pero hay que confirmarlo.
