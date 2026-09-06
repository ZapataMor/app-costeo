---
tags: [proyecto]
---

# Objetivo del Proyecto

> Actualizado 2026-07-06: **alcance confirmado por el usuario** (respuestas a [[Preguntas Abiertas]]). Ya no es especulativo.

## Objetivo de la tesis doctoral
Diseñar e implementar un **Sistema de Gestión del Conocimiento** ([[Sistema de Gestion del Conocimiento (SGC)]]) para **optimizar los costos del servicio de cirugía** de los 3 hospitales públicos de mediana complejidad de La Guajira (HSJ Maicao, HNR Riohacha, HSR San Juan del Cesar), elevando su madurez CMM-GC del Nivel 3 al 4-5 y reduciendo costos quirúrgicos entre 10% y 20%.

## Objetivo del aplicativo web (nuestra parte — alcance confirmado)
El usuario es el **desarrollador contratado del aplicativo completo**. El alcance es **doble**: la plataforma de costeo **y** las capas del SGC necesarias para conocer todo lo relacionado con los pacientes.

### Decisiones de alcance confirmadas (2026-07-06)
- **Multi-hospital y genérico**: se debe poder registrar **cualquier hospital o clínica**, cada uno con sus propias características, procedimientos, tecnologías y alcances (no todos tienen lo mismo). Los 3 hospitales de La Guajira son el punto de partida, no el límite.
- **A futuro**: módulo para **compartir información entre entes hospitalarios** dentro de la app (alineado con la "red departamental de GC" de la tesis).
- **Integración**: se buscará comunicación con los aplicativos existentes de cada hospital (HCE, facturación) — esa información es valiosa.
- **Parámetros de costos**: cada hospital suministra y administra sus propios datos (salarios, costo/minuto de sala, insumos).
- **Datos**: reales, brindados por cada hospital; el **Habeas Data (Ley 1581/2012)** se organizará con ellos.
- **Entregable**: **prototipo funcional + mediciones con datos reales durante algunos meses** de uso del aplicativo.

### Stack tecnológico
**Laravel + MySQL + React** (stack del usuario; compatible con lo que sugiere la tesis: web moderna, API REST, BD relacional). La definición fina de arquitectura se hará después. BI: por definir (la tesis sugiere Metabase/Superset como opciones open source).

### Módulos (según la arquitectura de la tesis, Fase C de TOGAF)
1. **Gestión de hospitales/clínicas** (registro configurable de cada ente: salas, personal, tarifas, procedimientos, tecnologías).
2. **Registro de tiempos quirúrgicos** por cirugía, rol y sala (referente: OptiSurge; tablet con modo offline).
3. **Captura de consumos** (medicamentos, insumos, esterilización, ropa quirúrgica) — digitaliza el Excel de tablas ([[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]]).
4. **Motor de costeo [[Costeo ABC y TDABC|ABC/TDABC]]**: parámetros de costo/minuto por recurso × tiempos + insumos = costo real por procedimiento.
5. **Dashboards/BI**: costo promedio y variabilidad por procedimiento/cirujano/período, utilización de salas, margen costo vs. tarifa (SOAT/contratada), KPIs Donabedian.
6. **Capas del SGC**: repositorio de conocimiento, lecciones aprendidas y lo necesario para la vista integral del paciente (detalle por priorizar).

## Hito inmediato
**Reunión con directivos del Hospital San José de Maicao (~2026-07-07/08)**: allí se conocerán los **10 procedimientos principales** del hospital (cada hospital visitado dará los suyos, pueden repetirse) y datos para la integración.

## Restricciones y estándares
- Códigos **CUPS** (procedimientos) y **CIE-10** (diagnósticos); interoperabilidad (HL7) como meta.
- Protección de datos personales (Ley 1581/2012 — Habeas Data con cada hospital).
- Sostenibilidad: preferir código abierto y bajo costo de operación (principio 7 de la tesis).
- Contexto real difícil: conectividad inestable, equipos limitados, alta rotación de personal.
