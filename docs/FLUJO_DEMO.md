# Recorrido de demostración — Controlab Documents

**Objetivo:** mostrar cómo evitar reingresar la misma información en Excel, papel y plantillas de informes. Este piloto representa el flujo, no reemplaza procedimientos oficiales.

**Publicación manual:** subir **solo** `public/index.html` como `public_html/controlab-demo/index.html` en un hosting autorizado con HTTPS. La publicación en cPanel **no** se actualiza automáticamente cuando cambia GitHub.

## Recorrido recomendado (5 minutos)

1. En **1. Preparar OT**, utiliza la empresa y obra ficticias de ejemplo o registra una empresa de demostración y una obra con su referencia/protocolo (nombre exacto por confirmar).
2. Crea una nueva **OT** seleccionando la obra. Observa que el formulario reutiliza los antecedentes de empresa y obra automáticamente, sin copiarlos desde Excel. Puedes abrir también la **OT-DEMO-001** preexistente.
3. En **2. Laboratorista**, ingresa las mediciones del ensayo de densidad, incluyendo muestra, ubicación, D.C.H., humedad (%) y D.M.C.S. La D.C.S. y el porcentaje de compactación son cálculos **preliminares** en pantalla. Con **+ 1 control** se agregan mediciones; cada seis controles se genera una página A4 en el informe.
4. Pulsa **Enviar borrador a validación**. Solo podrá avanzar si están indicadas OT, empresa, obra, muestra, ubicación y valores suficientes para calcular los controles.
5. En **3. Validador**, abre la bandeja. Puedes **Ver borrador**, **Devolver con observación** o **Aprobar (demo)**. Si lo devuelves, vuelve al ensayo, corrige el dato y reenvía. Si editas un borrador revisado, vuelve a necesitar revisión.
6. En **4. PDF**, utiliza **Imprimir / Guardar PDF**. El validador también puede imprimir un informe revisado desde su bandeja. Todos los PDF conservan marca de demostración y carecen de firma/certificación.

## Qué representa, y qué NO

- **Sí funciona en el navegador:** catálogos locales de empresas y obras, OT, ensayos, fórmulas preliminares, paginación 6 controles/hoja, generación de PDF mediante impresión, estados de trabajo simulados, observaciones, exportación/importación JSON y autosalvado local.
- **No funciona todavía entre personas/dispositivos:** todo lo anterior está en `localStorage` del mismo navegador y dominio. La simulación de administración, laboratorista y validador ocurre **en el mismo equipo**. No hay roles reales, autenticación por rol, asignación ni sincronización.
- **No hay validación oficial:** aprobar en la bandeja solo cambia un estado de la demo. No firma, certifica ni publica. Es necesario validar las fórmulas contra la plantilla/procedimiento autorizado, las reglas de redondeo y los campos exactos del registro de terreno antes de cualquier uso operativo.
- **No reemplaza al proveedor:** la publicación en el portal y la autorización del laboratorio quedan fuera del alcance de esta demostración.
- **No se autoriza usar datos reales:** utilizar únicamente información ficticia o expresamente autorizada, con medidas apropiadas para un entorno público.

## Prueba de guardado

Después de crear una OT, espera el mensaje «Guardado en este dispositivo», recarga la página y verifica que aparezca. Descarga un **respaldo JSON** antes de actualizar el HTML. Borrar datos del sitio, cambiar de navegador o usar navegación privada puede perder el estado local.

## Qué observar en el piloto

Medir, para el mismo ensayo ficticio, el tiempo desde el fin de la medición hasta el borrador listo para revisión en el flujo tradicional versus el flujo digital. Registrar transcripciones, retrabajo y correcciones. No afirmar ahorros sin medirlos.
