---
tags: [explicaciones, resumen]
descripcion: Resumen del conocimiento necesario para entender la finalidad del proyecto
---

# 📖 Explicaciones

> Resumen vivo del proyecto. **Actualización mayor del 2026-07-05** tras procesar los documentos del profesor: ahora sabemos exactamente qué quiere, porque leímos su libro y el capítulo 5 de su tesis doctoral.
> **Actualización del 2026-07-06**: llegó la **versión completa del Capítulo 5 en PDF (137 páginas)**. Trae tres regalos enormes para nosotros: el **modelo de datos del aplicativo ya definido** (entidades y relaciones), las **tecnologías sugeridas** (web moderna + API REST + BD relacional + BI open source) y los **6 portales por tipo de usuario**. Ver [[Fuente - Capitulo 5 Tesis Doctoral SGC]].
> **Actualización del 2026-07-11**: se procesaron **6 documentos externos** (literatura, no del profesor): un ebook didáctico de TDABC, 4 casos de ABC en cirugía/quirófano en hospitales latinoamericanos y una herramienta digital hospitalaria colombiana. Abren el "estado del arte" y confirman los patrones del modelo. Ver sección 9.

## 1. Qué es realmente el proyecto (versión corregida)

La tesis doctoral de [[Orlando Ruiz]] se titula:

> **"Sistema de Gestión del Conocimiento para la Optimización de los Costos del Servicio de Cirugía de los Hospitales de Mediana Complejidad del Departamento de La Guajira"**

Es decir: el proyecto **no es un sistema general de facturación de pacientes**, sino algo más específico:

1. **Dominio**: el servicio de **cirugía** de 3 hospitales públicos de La Guajira (Maicao, Riohacha y San Juan del Cesar).
2. **Problema**: los hospitales **no saben cuánto les cuesta realmente cada cirugía**. Negocian tarifas con las EPS a ciegas y a veces a pérdida (el libro del profesor demostró que el hospital de Maicao perdía hasta $94.310 por día de estancia en ciertas habitaciones sin saberlo).
3. **Solución de la tesis**: un [[Sistema de Gestion del Conocimiento (SGC)]] que capture datos quirúrgicos (tiempos, insumos, costos), los convierta en información (indicadores, dashboards) y en conocimiento (costos reales, variabilidad, mejores prácticas) para **reducir los costos quirúrgicos un 10-20%**.
4. **El aplicativo web** que nos piden es la materialización tecnológica de ese SGC — en particular la **plataforma de costeo ABC/TDABC** que hoy vive en hojas de Excel.

## 2. La historia en una frase
El profesor ya demostró en su libro (2022) que con [[Costeo ABC y TDABC|costeo ABC]] se puede calcular el costo real de un día de hospitalización en Excel. Su tesis doctoral escala esa idea al servicio de cirugía de 3 hospitales, envuelta en un sistema de gestión del conocimiento. **Nuestro trabajo es convertir ese modelo de Excel en un aplicativo web.**

## 3. Los tres conceptos que hay que dominar

### a) Costeo TDABC — "el costo por minuto"
Cada cirugía cuesta la suma de sus recursos por el tiempo que se usan:
`Costo = Σ(costo por minuto del recurso × minutos de uso) + insumos consumidos`
El costo/minuto del cirujano sale de su salario mensual ÷ minutos disponibles; el del quirófano, de depreciación + servicios + mantenimiento ÷ minutos disponibles (12 h/día × 26 días). Ver [[Costeo ABC y TDABC]].

### b) El instrumento de captura
El Excel del profesor ([[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]]) define QUÉ datos se capturan por cirugía: equipo humano y sus tiempos, medicamentos, insumos, esterilización, ropa quirúrgica, quirófano/minuto, depreciación de equipos, personal indirecto y logística. **Ese Excel es, en la práctica, el modelo de datos del futuro aplicativo.**

### c) Costo vs. tarifa = sobrevivir
El hospital cobra a las EPS con tarifas pactadas (en Maicao: **SOAT menos 25%**). Si el costo real supera la tarifa, el hospital pierde en silencio. El aplicativo hace visible ese margen por procedimiento — esa es su razón de ser financiera. Ver [[Sistema de Salud Colombiano]] y [[Cuenta del Paciente]].

## 4. ¿Y las complicaciones y el escalamiento de costos?
La intuición inicial (paciente que se complica → cuenta que se dispara) sigue siendo válida pero se reubica: en la tesis las complicaciones aparecen como **variabilidad de costos** (¿por qué la misma cirugía costó el doble?) y como **conocimiento a capturar** (lecciones aprendidas, tasas de complicación como KPI). La capa 4 del SGC contempla *modelos predictivos de costos* como meta avanzada (Nivel 5 de madurez), no como el punto de partida. Ver [[Complicaciones y Escalamiento de Costos]].

## 5. Datos duros para tener a mano
| Dato | Valor |
|---|---|
| Hospitales objetivo | HSJ Maicao · HNR Riohacha · HSR San Juan del Cesar |
| Madurez actual (CMM-GC, 1-5) | 2.755 · 3.149 · 3.429 (los tres = Nivel 3) |
| Dimensión más débil | "Aplicación en Costos" en los 3 hospitales |
| Procedimientos piloto TDABC | Cesáreas, apendicectomías, herniorrafias, colecistectomías, partos, legrados |
| Costo día de estancia HSJ (2019, libro) | General $153.027 · Pediatría $148.690 · Ginecología $215.810 |
| Meta de reducción de costos | 10-15% (Nivel 4) → 15-20% (Nivel 5) |
| Referente de software citado | OptiSurge (Colombia): utilización de salas de 61% → 75% en 10 días |
| Estándares obligatorios | CUPS (procedimientos), CIE-10 (diagnósticos), Ley 1581/2012 (datos personales) |

## 6. Qué módulos tendría el aplicativo (según la Fase C de la tesis)
1. **Registro de tiempos quirúrgicos** (quién, qué, cuánto duró — por rol y por sala; la tesis pide app en **tablet con modo offline** para la sala de cirugía).
2. **Captura de consumos** (medicamentos, insumos por cirugía, con búsqueda por código/nombre).
3. **Motor de costeo ABC/TDABC** (parámetros de costo/minuto + tiempos = costo real).
4. **Dashboards / BI** (costo promedio, variabilidad, utilización de salas, margen vs. tarifa).
5. **Repositorio de conocimiento** (protocolos, lecciones aprendidas) y KPIs del SGC.

### 6b. Lo que la versión completa del PDF ya nos dio resuelto (2026-07-06)
- **Modelo de datos relacional** (sección 5.3.3): entidades Paciente, Procedimiento (CUPS), Cirugía, Equipo Quirúrgico (con tiempos por rol), Insumo, Consumo, Equipo Médico, Sala Operatoria, Costo de Cirugía y Resultado Clínico. Es prácticamente el diagrama entidad-relación del aplicativo.
- **Stack sugerido**: React/Vue/Angular + API REST + PostgreSQL/MySQL + BI open source (Metabase/Superset) + nube. El software de costeo lo describe como **"desarrollo a medida"** — ese desarrollo somos nosotros.
- **6 portales por usuario**: ejecutivo, operativo (tiempo real de salas), individual del cirujano (benchmarking anónimo, no punitivo), de costos (margen costo vs. tarifa), comunidades de práctica y público.
- **Ejemplo de cálculo verificable**: una cesárea por TDABC = $520.000 COP (desglosado por recurso) — sirve como caso de prueba del motor de costos.
- **Validaciones exigidas**: completitud, consistencia, integridad referencial y detección de outliers en la captura.

## 7. Alcance confirmado (2026-07-06) ✅
El usuario (desarrollador **contratado** para construir el aplicativo completo) respondió las preguntas de alcance:

| Decisión | Respuesta |
|---|---|
| Alcance | **Costeo + capas del SGC** (vista integral del paciente): ambas cosas |
| Modelo | **Multi-hospital genérico**: cualquier hospital/clínica se registra con sus propios procedimientos, tecnologías y características |
| Futuro | Módulo para **compartir información entre entes hospitalarios** |
| Integración | Sí — comunicarse con los sistemas existentes de cada hospital |
| Stack | **Laravel + MySQL + React** (stack del usuario) |
| Parámetros de costos | Los suministra **cada hospital** |
| Datos | **Reales**, brindados por los hospitales; Habeas Data se organiza con ellos |
| Entregable | **Prototipo funcional + mediciones reales durante algunos meses** |

**Hito inmediato**: reunión con directivos del **Hospital San José de Maicao (~7-8 de julio de 2026)** para conocer sus **10 procedimientos principales** (cada hospital dará los suyos).

## 8. Lo que aún no sabemos
Ver [[Preguntas Abiertas]] — ya solo faltan detalles: los 10 procedimientos por hospital (salen de las reuniones), qué sistemas usa cada hospital para la integración, cuántos meses de medición exige el entregable, y la arquitectura fina sobre Laravel/MySQL/React.

## 9. Lo que aprendimos de la literatura externa (2026-07-11)

Seis documentos nuevos, y cuatro lecciones que cambian o refuerzan cómo construiremos el aplicativo:

### a) La matemática fina del TDABC ya está clara
El [[Fuente - Ebook TDABC (Kaplan y Anderson)|ebook de TDABC]] (basado en Kaplan & Anderson, los creadores del método) precisa tres cosas que el motor de costos debe implementar:
- El costo/minuto se calcula sobre **capacidad práctica** (~80% de la jornada en personas, ~85% en máquinas), no sobre la teórica.
- Las variantes de una cirugía (complicación, conversión, reintervención) se modelan como **ecuaciones de tiempo** (términos aditivos), no como procedimientos distintos.
- El costo de la **capacidad no utilizada** (quirófanos ociosos) queda visible como reporte propio — conecta con el KPI de utilización de salas (el referente OptiSurge).

### b) Los casos reales confirman el patrón — y los números
Cuatro casos de ABC en cirugía en Latinoamérica ([[Fuente - Caso ABC Hospital Regional de Talca (Chile)|Talca, Chile]]; [[Fuente - Tesis ABC Quirofano Hospital San Juan de Dios (Cuenca)|San Juan de Dios]] y [[Fuente - Tesis Gestion Costos Cirugias Hospital del Nino y la Mujer (Cuenca)|Hospital del Niño y la Mujer]], Cuenca; [[Fuente - Tesis ABC Quirofano Hospital Leon Becerra (Guayaquil)|León Becerra, Guayaquil]]) repiten lo mismo que el libro del profesor: hospitales que no conocen sus costos, **25-40% del costo escondido en actividades de apoyo**, mano de obra como rubro dominante (>75% en San Juan de Dios), y la recomendación explícita (León Becerra, 2015) de que la solución es **un software integrado** — o sea, lo que vamos a construir. Bonus: las cirugías representativas de esos hospitales (cesárea, colecistectomía, apendicectomía) coinciden con nuestros pilotos.

### c) Dos indicadores nuevos candidatos para los dashboards
- **Tiempos de espera del paciente** (Talca midió ~14 h perdidas por paciente en el proceso de cataratas) — sale casi gratis de la captura de tiempos.
- **Punto de equilibrio quirúrgico** (Hospital del Niño y la Mujer: mínimo 27 cirugías/mes según la mezcla) — responde "¿cuánto volumen necesita el hospital para sostenerse?".

### d) Requisitos probados para el módulo de consumos/insumos
La [[Fuente - Tesis Herramienta Digital Compras Biomedicas HAMA (Medellin)|herramienta del Hospital Alma Máter (Medellín, 2024)]] — aunque es un Excel con macros — valida en un hospital colombiano el diseño que necesitamos: **catálogo maestro de referencias con buscador y autocompletado, estados de pedido calculados automáticamente, listas desplegables parametrizables y dashboard por servicio/proveedor/mes**. También su fórmula de adopción: importar el histórico como prueba + instructivo + video.

**Lo que estos documentos NO cubren**: nada nuevo sobre tarifas SOAT / sistema colombiano (los casos son de Chile y Ecuador) ni sobre la capa SGC — ahí seguimos dependiendo del capítulo 5 del profesor.

---
*Última actualización: 2026-07-11 — procesados: libro ABC (155 págs.), capítulo 5 de la tesis (versión completa PDF de 137 págs.), Excel de tablas de costos, certificados, y 6 documentos externos (ebook TDABC, 4 casos ABC quirúrgicos LATAM, herramienta digital HAMA).*
