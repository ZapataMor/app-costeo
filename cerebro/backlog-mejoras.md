---
tags: [backlog]
actualizado: 2026-09-15
---

# Backlog de mejoras

> Lista numerada y **nunca renumerada**: el número es el identificador ("implementa la mejora #N"). Al terminar una, márcala `✅ hecha` con enlace a la nota de bitácora. Prioridad: **P1** (bloquea el valor del producto o la tesis) · **P2** (mejora clara, no urgente) · **P3** (deseable).
>
> Formato de cada fila: `#N | mejora | agente que la propuso | prioridad | estado`.
> Las entradas iniciales vienen de la migración del cerebro (fuentes y notas previas), no de una corrida de agentes; el campo "agente" indica de qué rol es la idea.

| # | Mejora | Agente | Prioridad | Estado |
|---|---|---|---|---|
| 1 | **Alinear los umbrales de la alerta de sobrecosto** con la clasificación del pipeline doctoral: `Subejecucion` ≤ −5 %, `En_Rango`, `Sobrecosto_Leve` 5-20 %, `Sobrecosto_Alto` > 20 % (`DetectorSobrecostos`). Fuente: [[Fuente - Guia de Campos Base de Datos TDABC+MILP]]. | [[experto-contabilidad-costos]] | P1 | pendiente |
| 2 | **Reporte de capacidad no utilizada** por sala y por recurso (capacidad práctica − minutos usados, valorada en COP). Es el KPI gerencial propio del TDABC y equivale a `costo_capacidad_ociosa_cop` del pipeline. Fuente: [[Fuente - Ebook TDABC (Kaplan y Anderson)]]. | [[experto-contabilidad-costos]] | P1 | pendiente |
| 3 | **Costo estándar vs. costo real por cirugía**: calcular el costo con la duración planificada del procedimiento (`duracion_estimada_minutos`, tiempos por fase de la plantilla) y mostrar la desviación junto al real. Base para #1 y #2. | [[experto-contabilidad-costos]] | P1 | pendiente |
| 4 | **Horas extra**: sobrecosto del 50 % en el costo de capacidad para cirugías que pasan de una hora de corte configurable por hospital (el pipeline usa las 15:00). Requiere confirmar con el profesor ([[preguntas-abiertas]]). | [[experto-contabilidad-costos]] | P2 | pendiente |
| 5 | **Factor de ineficiencia institucional** configurable por hospital (1,28 · 1,20 · 1,15 en el pipeline) que multiplica el costo de capacidad, no los materiales. Solo si el profesor confirma que va en el aplicativo. | [[experto-contabilidad-costos]] | P2 | pendiente |
| 6 | **Ecuaciones de tiempo** por procedimiento: tiempo base + términos aditivos condicionales (complicación, conversión laparoscópica → abierta, reintervención) en la plantilla del protocolo, en vez de crear procedimientos distintos. | [[experto-procedimientos-medicos]] | P2 | pendiente |
| 7 | **Punto de equilibrio quirúrgico mensual**: nº mínimo de cirugías según la mezcla histórica, a partir de costos fijos/variables y márgenes de contribución. Fuente: [[Fuente - Tesis Gestion Costos Cirugias Hospital del Nino y la Mujer (Cuenca)]]. | [[analista-negocio]] | P2 | pendiente |
| 8 | **Tiempos de espera del paciente** como subproducto de las marcas de fase (entrada a sala, incisión, cierre, salida de recuperación): esperas de alistamiento y aseo entre cirugías, cancelaciones. Fuente: [[Fuente - Caso ABC Hospital Regional de Talca (Chile)]]. | [[experto-procesos-hospitalarios]] | P2 | pendiente |
| 9 | **Portal individual del cirujano** (capa 5 de la tesis): indicadores propios con benchmarking **anónimo** y enfoque no punitivo. Hoy el cirujano no es usuario del sistema. | [[ux-ui]] | P2 | pendiente |
| 10 | **Capa 4 — lecciones aprendidas**: al cerrar una alerta de sobrecosto con causa, permitir registrar la lección y consultarla por procedimiento. Primer paso de las "comunidades de práctica" de la tesis. | [[analista-negocio]] | P2 | pendiente |
| 11 | **Captura en sala con tablet y modo offline** (botones grandes, marcas de tiempo con un toque, sincronización posterior). La tesis lo pide explícitamente; el contexto real tiene conectividad inestable. | [[ux-ui]] | P3 | pendiente |
| 12 | **Integración con el HIS/HCE de cada hospital** (importación de archivos planos periódica como primer paso; HL7/API después). Depende de las respuestas del bloque 5 de [[preguntas-levantamiento-hospital]]. | [[arquitecto]] | P3 | pendiente |
| 13 | **Catálogo de insumos con código de barras / CUM** y autocompletado; manejo de consignación, desperdicio y devoluciones. Fuente: [[Fuente - Tesis Herramienta Digital Compras Biomedicas HAMA (Medellin)]] y bloque 2 del cuestionario. | [[experto-procesos-hospitalarios]] | P3 | pendiente |
| 14 | **Actualizar el README**: dice 61 tests y "Capa 1 = parámetros"; hoy son 299 tests y existen alertas, plantillas, fases, CIF por bolsas, roles. | [[arquitecto]] | P2 | pendiente |
| 15 | **Hacer privado el repositorio o depurar el material sensible del historial** (cotización, PDFs inéditos de la tesis, correo del profesor). Ver [[cliente-y-negocio#11. Confidencialidad ⚠️]]. | [[qa-seguridad]] | P1 | pendiente — decisión del usuario |
| 16 | **Portal ejecutivo consolidado multi-hospital** con semáforos y exportación PDF/Excel (hoy el `dashboard` del super_admin es básico y la exportación es solo CSV). | [[analista-negocio]] | P3 | pendiente |
| 17 | **Módulo de comparación entre hospitales** (red departamental de GC): compartir indicadores agregados y anónimos entre entes. Alcance confirmado "a futuro" por el usuario. | [[analista-negocio]] | P3 | pendiente |
| 18 | **Indicadores operativos de la *Fundamentación***: turnover time entre cirugías por sala, tasa de cancelación, tasa y costo de horas extra, espera solicitud → cirugía electiva, latencia cirugía → registro (< 7 días). Tienen fórmula, línea base y meta definidas. Fuente: [[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]] §8. Amplía #4 y #8. | [[analista-negocio]] | P2 | pendiente |
| 19 | **Dataset de validación con las 3.100 cirugías simuladas**: seeder/fixture opcional que importe `Base_Datos_3000_Cirugias_TDABC_MILP.xlsx` (15 procedimientos, tres hospitales, 36 meses) y test que compare el costo del motor con `costo_tdabc_real_cop` usando las tasas del pipeline. Prueba el motor con volumen realista y fija la equivalencia con la tesis. Fuente: [[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]]. | [[qa-seguridad]] | P2 | pendiente |
| 20 | **Campos clínicos del segundo modelo de datos**: clasificación ASA, comorbilidades, peso/talla/IMC, prioridad electiva/urgente/emergente, diagnóstico postoperatorio, hora programada vs. real, tiempo de anestesia, y entidad de **servicios de apoyo** (laboratorio, imágenes, banco de sangre, patología) por cirugía. Fuente: [[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]] §5.1. Validar con [[experto-procedimientos-medicos]] qué es imprescindible. | [[experto-procedimientos-medicos]] | P3 | pendiente |
| 21 | **Aclarar en la UI y docs la numeración de capas**: el aplicativo dice "Capa 1 = parámetros", la tesis "Capa 1 = objetos" y Kerschberg tiene tres capas. Unificar con la numeración de la tesis y citar el artículo original. Fuente: [[Fuente - Articulo Kerschberg Knowledge Management Data Warehouse]]. Se puede resolver junto con #14. | [[arquitecto]] | P3 | pendiente |

## Hechas

*(ninguna todavía — las mejoras cerradas se mueven aquí con su enlace a la bitácora)*
