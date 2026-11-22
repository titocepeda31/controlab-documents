# Módulo propuesto: Planificador de órdenes de trabajo y salidas a terreno

Estado: **diseño funcional preliminar**, separado del piloto de informes de suelo de Forestal 3M. No implementar automatizaciones ni módulos técnicos que aún no han sido definidos con personal del laboratorio.

## Problema operativo
Hoy se reciben solicitudes por correo, se revisan para obtener los datos de obra y luego se programan en papel los trabajadores, equipos y servicios en terreno. La información se repite y no hay un calendario central accesible. El piloto actual de Roberto/Forestal 3M es un sistema de informes; este módulo sería complementario.

## Entidades

1. **Empresa / cliente** (razón social, contacto, correo, estado): una empresa tiene muchas obras.
2. **Obra** (nombre, empresa_id, dirección/comuna/referencia de acceso, coordenadas si se proporcionan, responsable de contacto, teléfono): una obra puede generar muchas órdenes.
3. **Solicitud** (fecha de correo o solicitud, remitente, motivo, adjuntos, estado de recepción): etapa de pre-registro. Inicialmente creación manual y opcional adjuntar el correo o respaldo; integración de lectura automática de correos es futura y requiere autorización expresa y conexión segura. No atribuir un cliente automáticamente si no está confirmado.
4. **Orden de trabajo / OT** (número único, obra_id, fecha requerida, prioridad, instrucciones internas, estado, persona creadora, origen de solicitud): una OT puede contener **varios servicios**.
5. **Tipo de servicio** configurable (nombre, categoría, duración estimada opcional, equipamiento típico). No fijar catálogo hasta confirmar nombres internos; ejemplos referenciales: densidad en terreno, extracción de testigos, calicatas, toma de muestras.
6. **Servicio de OT** (ot_id, tipo_servicio_id, cantidad de puntos/muestras si procede, indicaciones).
7. **Visita / salida a terreno** (ot_id, fecha, hora inicio y fin estimadas, lugar de encuentro, instrucciones de acceso, estado): una OT puede necesitar varias salidas.
8. **Asignación de personal** (visita_id, usuario_id, función asignada): una salida puede tener varias personas y una persona varias visitas en el tiempo.
9. **Equipos / recursos** (código, nombre, estado operativo, fecha de calibración/mantenimiento si aplica).
10. **Asignación de equipo** (visita_id, equipo_id): evitar doble reserva simultánea, alertar por equipo indisponible.
11. **Resultado o parte de terreno** (visita_id, fecha de ejecución, estado, observaciones técnicas y fotografías opcionales; idealmente **datos escritos en campos**, no solo fotos de manuscritos).
12. **Informes** (vinculación opcional a OT y servicio). El formato específico depende de cada tipo de ensayo. No cambiar el PDF piloto sin recibir la plantilla original.

## Navegación (sin modales)
- **Empresas:** CRUD y obras asociadas a cada empresa.
- **Obras:** ficha y listado de OT de esa obra, dirección verificable.
- **Órdenes de trabajo:** crear OT, asociar uno o varios tipos de servicio; recepción, programación, seguimiento.
- **Calendario:** vistas día, semana y mes, con filtros por laboratorista, obra, equipo y estado; alerta de conflictos de horario y recursos.
- **Mi agenda:** pantalla simplificada para cada laboratorista con OT, fecha, hora, ubicación, tipo de servicio y compañeros/equipos asignados. **No mostrar cliente ni datos comerciales** si su trabajo no los requiere.
- **Terreno:** marcar inicio y cierre de visita, capturar lecturas/datos e incidencias; fotos solo de apoyo y según autorización.
- **Informes y entregas:** conectar cada informe al trabajo de la OT y dejar traza de quién generó y envió el documento.

## Flujo
Correo recibido -> recepción y validación manual -> Empresa y obra existentes o nuevas -> OT con servicio(s) -> planificar visita(s) y asignar personas/equipos -> equipo visualiza 'Mi agenda' -> ejecutar en terreno -> registrar datos -> elaboración/validación de informe según tipo -> entrega al cliente autorizado.

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
- ¿Quién asigna el número de OT y existe numeración oficial que debamos respetar?
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
