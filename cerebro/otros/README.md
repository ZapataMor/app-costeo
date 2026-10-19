---
tags: [moc, otros]
---

# otros/ — conocimiento de dominio migrado

Todo lo que no encaja en la estructura fija del cerebro (resumen, cliente, backlog, decisiones, bitácora, agentes) vive aquí. Es el **conocimiento de dominio** del vault anterior (`información-contexto/Cerebro`, migrado el 2026-09-15 sin pérdida): los agentes de dominio lo usan como base.

## Conceptos (`conceptos/`)
- [[Costeo ABC y TDABC]] ⭐ — la metodología contable central.
- [[Costos Indirectos (CIF)]] ⭐ — bolsas por inductor, doble conteo y estado en el aplicativo.
- [[Sistema de Gestion del Conocimiento (SGC)]] ⭐ — el marco de la tesis (Kerschberg, TOGAF, CMM-GC).
- [[Cuenta del Paciente]] — la "hoja de vida" financiera.
- [[Cotizacion de Procedimientos]] — costo esperado vs. real; tarifario SOAT.
- [[Complicaciones y Escalamiento de Costos]] — variabilidad de costos.
- [[Sistema de Salud Colombiano]] — EPS, IPS, regímenes, glosas.
- [[Glosario]] — CUPS, RIPS, UPC, capacidad práctica, etc.

## Actores (`actores/`)
[[Paciente]] · [[Medico]] · [[Hospital o Clinica (IPS)]] · [[Asegurador (EPS)]] · [[Area de Facturacion]] · [[Gobierno (ADRES)]]

## Fuentes (`fuentes/`)
Una nota por documento procesado (origen, resumen, aportes, citas). Los archivos originales están en `fuentes/originales/` organizados en `profesor/`, `literatura/` y `proyecto/` — índice en [[originales/README|originales]].

**Del profesor:**
- [[Fuente - Capitulo 5 Tesis Doctoral SGC]] ⭐ — el documento clave: modelo de datos, portales, stack sugerido, cesárea de $520.000.
- [[Fuente - Guia de Campos Base de Datos TDABC+MILP]] ⭐ — fórmulas y tasas exactas del pipeline (CIF 420 COP/min, 80 % capacidad, umbrales de desviación).
- [[Fuente - Libro Costeo ABC Hospital San Jose de Maicao]] — libro del profesor (2022).
- [[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]] — por qué existe el pipeline TDABC+MILP; segundo modelo de datos; 20 indicadores con meta.
- [[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]] — el Excel de 3.100 cirugías simuladas: cifras, 15 tipos de cirugía, fórmulas de KPIs.
- [[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]] — el modelo de datos en embrión.
- [[Fuente - Documentos administrativos del libro]]

**Literatura externa:**
- [[Fuente - Ebook TDABC (Kaplan y Anderson)]] — la matemática del método.
- [[Fuente - Caso ABC Hospital Regional de Talca (Chile)]] — piloto con 44 casos reales.
- [[Fuente - Tesis ABC Quirofano Hospital San Juan de Dios (Cuenca)]] — 17 actividades, 5 cirugías representativas.
- [[Fuente - Tesis ABC Quirofano Hospital Leon Becerra (Guayaquil)]] — centros de costos y minutos por actividad.
- [[Fuente - Tesis Gestion Costos Cirugias Hospital del Nino y la Mujer (Cuenca)]] — ABC + punto de equilibrio.
- [[Fuente - Tesis Herramienta Digital Compras Biomedicas HAMA (Medellin)]] — módulo de compras/insumos en Colombia.
- [[Fuente - Articulo Kerschberg Knowledge Management Data Warehouse]] — el artículo original del "modelo de Kerschberg"; aclara qué es de Kerschberg y qué es adaptación de la tesis.

**Del proyecto** (en `originales/proyecto/`): la cotización de julio 2026 → [[cliente-y-negocio#10. Acuerdo comercial]]; el cuestionario de visita → [[preguntas-levantamiento-hospital]].

Todos los documentos recibidos hasta el 2026-09-15 tienen nota. Documentos nuevos: ver [[bandeja-de-entrada]].

## Otras notas
- [[preguntas-abiertas]] — dudas pendientes y respondidas, con historial.
- [[preguntas-levantamiento-hospital]] — cuestionario de 50 preguntas para la visita institucional (2026-07-22).
- [[bandeja-de-entrada]] — cómo se procesan documentos nuevos del profesor.

## Convención de las notas de fuente
- Una nota por documento: `Fuente - <nombre corto>.md`.
- Cada nota lleva **Origen** (archivo y fecha de procesamiento), **Resumen**, **Aportes al cerebro** (qué conceptos/actores se actualizaron) y **Citas útiles** (para la tesis del profesor, citar bien importa).
