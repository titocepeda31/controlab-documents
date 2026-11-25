# Núcleo técnico de laboratorio — etapa 1
1. Respaldar MySQL. Ejecutar primero vincular_servicios_ensayos.sql si falta y luego crear_nucleo_laboratorio.sql.
2. Actualizar Git y desplegar en cPanel. El script comprueba las tablas.
3. Abrir Laboratorio como administrador/administrativo. Elegir obra y crear material y muestra. Para punto se requiere ID de una OT no cancelada de la misma obra. Registrar resultados y recargar: persisten cifrados.
4. Catálogo 34 tipos y matriz descriptiva 18 informes. 11 tipos de captura parcial (solo demostración); S01 no es granulometría completa. S03/S04/S09/S10/S11/S12/S13 transcriben resultados ya medidos, no ejecutan cálculo de norma. S02 y S14/S15 calculan humedad y DCS en servidor.
5. Tablas de estudios/referencias/versiones preparadas; aún no hay API de aprobación, corrección, informe oficial ni enlace al constructor. Los registros son borradores; no hay decisión de cumplimiento. No usar datos reales en piloto sin validación. Identificadores y nombres de material/muestra se almacenan como metadatos consultables; cargas técnicas cifradas con contexto distinto de los informes antiguos.
6. Servicios mantiene sus 17 códigos de formularios; se requiere correspondencia explícita, variantes y cantidades por unidad comercial, e histórico antes de automatizar OT. Los resultados no se facturan en esta etapa.
7. Prueba unitaria: php mvc/backend/tests/LaboratorioTest.php. Probar integración MySQL, permisos y navegador en entorno de desarrollo.
