# Constructor de informes — piloto
Ruta pública: `/demo/informes.html`. Usa el shell y sesión existentes; no sustituye `formulario.html`.
- Catálogo inicial de 17 tipos de ensayo; resultados en borrador en memoria, repetibles por muestra o punto.
- Consulta opcional de OT existentes con el endpoint autorizado GET /api/ot; no modifica datos. Selección de ensayos manual hasta definir relación servicio–ensayo.
- Vista previa e impresión con marca NO VÁLIDO. Usar solo datos ficticios; no hay almacenamiento, firma, correlativo, facturación, cálculo normativo completo ni emisión oficial.
- Fórmulas orientativas: DCS, compactación, densidad relativa, IP y separación grueso/fino según tamiz de referencia del piloto. Se debe validar norma y edición, unidades, redondeo y formato con responsable técnico.
- Pendiente: inventario de plantillas, reglas de métodos, catálogo acreditado, esquema persistente cifrado, autorización por OT, auditoría y revisión.
## Prueba manual
Entrar con sesión, abrir Informes y pulsar Cargar ejemplo ficticio. Deben aparecer cinco secciones; IP 14,8 %, fracción fina 42 %, DCS 2,146 g/cm³ y compactación 98,4 % (aprox.). Cambiar DMCS a 1,82: aviso de compactación superior a 100 %. Agregar otra densidad, eliminar una sección, verificar vista previa e impresión. Consultar OT de un rango y comprobar servicios. Recargar: los resultados de prueba desaparecen.

El registro anterior queda fuera de la navegación del constructor y del menú principal. Se conserva su URL y código, incluido el login compartido, para una futura integración independiente.
