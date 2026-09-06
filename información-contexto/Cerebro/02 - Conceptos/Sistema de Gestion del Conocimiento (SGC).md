---
tags: [concepto, nucleo-del-proyecto]
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
