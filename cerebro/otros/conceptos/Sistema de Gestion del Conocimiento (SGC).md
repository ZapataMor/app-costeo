---
tags: [concepto, nucleo-del-proyecto]
migrado_de: "información-contexto/Cerebro/02 - Conceptos/Sistema de Gestion del Conocimiento (SGC).md"
---

# Sistema de Gestión del Conocimiento (SGC)

**El marco general de la tesis doctoral** ([[Fuente - Capitulo 5 Tesis Doctoral SGC]]). El aplicativo web no es solo un sistema de costos: es la pieza tecnológica de un SGC para el servicio de cirugía de 3 hospitales de La Guajira.

## Idea central
El conocimiento (cuánto cuesta una cirugía, por qué varía, qué prácticas funcionan) hoy está disperso en Excel, papeles y en la cabeza de la gente (**conocimiento tácito**). El SGC lo captura, lo estructura y lo devuelve como decisiones: `datos → información → conocimiento → optimización de costos`.

## Arquitectura: modelo de Kerschberg (5 capas)
| Capa | Qué es | En el aplicativo |
|---|---|---|
| 1. Objetos | Repositorio de documentos | Protocolos, guías, registros operatorios |
| 2. Datos | Captura estructurada | **Tiempos quirúrgicos, consumos, costos** (CIE-10, CUPS) |
| 3. Información | Indicadores y análisis | **BI**: utilización de salas, costo promedio, variabilidad |
| 4. Conocimiento | Patrones y predicción | Modelos predictivos de costos, lecciones aprendidas |
| 5. Presentación | Interfaces por usuario | **Dashboards** gerencia / jefes de cirugía / cirujanos |

> ⚠️ **Sobre la atribución** (2026-09-15): el artículo original de Kerschberg (2001) describe **tres capas** (presentación, gestión del conocimiento, fuentes de datos) y **cinco actividades de proceso** (adquisición, refinamiento, almacenamiento/recuperación, distribución, presentación). Las cinco capas de arriba son la **adaptación de la tesis**; la *Fundamentación* usa una tercera numeración. Al citar, distinguir. Ver [[Fuente - Articulo Kerschberg Knowledge Management Data Warehouse]].

Metodología de implementación: **TOGAF ADM** (fases A-H). Medición de madurez: modelo **CMM-GC de 5 niveles** con el instrumento IMNCGC-CMM (creado y validado en la tesis).

## Componentes operativos (sección 5.4 de la tesis)
1. Comunidades de práctica quirúrgica.
2. Sistema de lecciones aprendidas.
3. **Plataforma de costeo ABC/TDABC** ← el módulo más "de software" (ver [[Costeo ABC y TDABC]]).
4. Sistema de indicadores/KPIs (estructura-proceso-resultado, marco Donabedian).
5. Programa de formación y cultura.
6. Gobierno del conocimiento (comités, roles).

## Los 3 hospitales objetivo
- **HSJ** San José de Maicao (madurez 2.755) — intervenido por Supersalud.
- **HNR** Nuestra Señora de los Remedios, Riohacha (3.149).
- **HSR** San Rafael Nivel II, San Juan del Cesar (3.429) — el más maduro, referente.

Los tres están en Nivel CMM 3; la meta es llevarlos a Nivel 4 (y HSR a 5) en 36 meses, con reducción de costos quirúrgicos del 10-20%.

## El tercer objetivo: base de datos de 3.000+ cirugías y optimización TDABC + MILP
La tesis incorporó un objetivo adicional ([[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]]): construir una base de datos de más de 3.000 cirugías de los tres hospitales, costearlas por TDABC y optimizar la programación con MILP (con ML para predecir duraciones). El pipeline ya produjo una base **simulada** de 3.100 cirugías ([[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]]) con las tasas de la [[Fuente - Guia de Campos Base de Datos TDABC+MILP]]. **El aplicativo es la fuente natural de la base de datos real** que reemplazará a la simulada; el MILP y el ML son análisis de la tesis, no módulos del aplicativo por ahora.
