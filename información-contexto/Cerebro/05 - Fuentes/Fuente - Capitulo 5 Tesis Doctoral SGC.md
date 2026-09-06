---
tags: [fuente, tesis-doctoral, nucleo-del-proyecto]
origen: "Capitulo_5_SGC_Propuesta_Hospitales_Guajira (1).pdf (versión completa, 137 págs.) — reemplaza al .docx procesado antes"
procesado: 2026-07-06
---

# Fuente — Capítulo 5 de la Tesis Doctoral del profesor (versión completa)

> 🔑 **Este es el documento más importante del cerebro.** Es el capítulo propositivo de la tesis doctoral de [[Orlando Ruiz]]:
> **"Sistema de Gestión del Conocimiento para la Optimización de los Costos del Servicio de Cirugía de los Hospitales de Mediana Complejidad del Departamento de La Guajira"**
>
> 📌 El 2026-07-06 llegó la **versión completa en PDF (137 páginas)**, mucho más detallada que el Word procesado el día anterior. Esta nota integra ambas. Original: `originales/Capitulo_5_SGC_Propuesta_Hospitales_Guajira (1).pdf`.

## Qué propone la tesis
Un **Sistema de Gestión del Conocimiento (SGC)** — ver [[Sistema de Gestion del Conocimiento (SGC)]] — para optimizar los **costos del servicio de CIRUGÍA** en los 3 hospitales públicos de mediana complejidad de La Guajira:
- **HSJ** — ESE Hospital San José de Maicao (intervenido por Supersalud; puntaje CMM 2.755, el más bajo)
- **HNR** — ESE Hospital Nuestra Señora de los Remedios de Riohacha (3.149; hospital de referencia, llamado a liderar la red)
- **HSR** — ESE Hospital San Rafael Nivel II de San Juan del Cesar (3.429; el más maduro, aspira a Nivel 5 y a ser "mentor")

## Diagnóstico (capítulo 4, referenciado aquí)
- Instrumento propio (**IMNCGC-CMM**): los 3 hospitales están en **Nivel 3 de 5** ("Definido/Consciente").
- La dimensión más débil en los tres es **"Aplicación en Costos"** (HSJ=2.548, HNR=2.884, HSR=3.175) → nadie captura ni usa sistemáticamente datos de costos quirúrgicos.
- ANOVA confirmó diferencias significativas entre hospitales (F=852.303, p<0.001) → por eso la tesis propone **estrategias diferenciadas por hospital** (sección 5.6).

## Arquitectura: Kerschberg (5 capas) + TOGAF ADM
1. **Objetos**: repositorio unificado de documentos/protocolos quirúrgicos con taxonomías y metadatos.
2. **Datos**: captura de tiempos quirúrgicos, consumos, costos (ver modelo de datos abajo).
3. **Información**: plataforma de BI (analítica descriptiva y comparativa; la predictiva y prescriptiva son fases avanzadas Nivel 4-5).
4. **Conocimiento**: comunidades de práctica, lecciones aprendidas, mejores prácticas, minería de datos, gestión de protocolos.
5. **Presentación**: 6 portales por tipo de usuario (ver abajo).

## 🗃️ Modelo de datos explícito (sección 5.3.3 — ¡el esquema del aplicativo!)
El PDF define un **modelo relacional** con estas entidades:
- **PACIENTE** — identificación, demografía, aseguramiento.
- **PROCEDIMIENTO_QUIRURGICO** — código CUPS, especialidad, complejidad, duración estimada.
- **CIRUGIA** — fecha, hora inicio/fin, sala, tipo (programada/urgencia). Relaciones: Paciente 1:N, Procedimiento N:M, Equipo 1:N.
- **EQUIPO_QUIRURGICO** — cirujano, ayudantes, anestesiólogo, instrumentador, circulante + tiempos de participación de cada uno.
- **INSUMO** — código, unidad, costo unitario, categoría (medicamento/dispositivo/material).
- **CONSUMO_INSUMO** — cirugía × insumo, cantidad, costo total.
- **EQUIPO_MEDICO** — equipos usados (laparoscopio, electrobisturí…), tiempo de uso, costo/hora.
- **SALA_OPERATORIA** — identificación, equipamiento, costo/hora.
- **COSTO_CIRUGIA** — costos directos e indirectos, costo total.
- **RESULTADO_CLINICO** — complicaciones intra/postoperatorias, estancia, reingreso, mortalidad.

Estándares: **CUPS** (procedimientos), **CIE-10** (diagnósticos), **ATC** (medicamentos), códigos institucionales para insumos/salas. Validaciones automáticas: completitud, consistencia, integridad referencial y **detección de outliers**.

### Fuentes de captura (prioriza lo automatizado)
HCE (vía HL7/APIs), programación quirúrgica, **sistema de registro de tiempos a implementar** (tablets en sala, con botones grandes y **modo offline**), inventarios, facturación, y formularios estructurados para lo demás (p. ej. complicaciones con listas desplegables). Incluye un **dashboard de monitoreo de completitud de captura** para supervisores.

## 💰 Costeo híbrido ABC/TDABC (Componente 3, sección 5.4.3)
- **TDABC** para alto volumen: cesáreas, apendicectomías, herniorrafias, colecistectomías, partos, legrados.
  - `Costo total = Σ(costo/hora del recurso × tiempo de uso) + costo de insumos`
  - Costo/hora personal = (salario + prestaciones + indirectos) ÷ horas disponibles; sala = (depreciación + mantenimiento + servicios + limpieza + indirectos) ÷ horas disponibles.
- **ABC clásico** para cirugías complejas (oncológicas, reconstructivas): 7 actividades (preparación preoperatoria → anestesia → preparación quirúrgica → incisión → procedimiento → hemostasia/cierre → recuperación) con cost drivers.
- **Ejemplo numérico de cesárea (COP)**: cirujano $50.000/h ×1.5h + ayudante $30.000×1.5 + anestesiólogo $50.000×2 + instrumentador $20.000×2 + circulante $15.000×2 + sala $40.000×2 + insumos $150.000 = **$520.000**.
- Módulos de la plataforma: captura de tiempos, captura de insumos, **motor de cálculo automático**, base de datos de costos, análisis de variabilidad, integración con BI.
- Implantación de la plataforma en 4 fases: diseño del modelo (meses 1-3, con 3-5 procedimientos piloto) → pilotaje (4-6) → escalamiento (7-12) → consolidación (13-24).
- Objetivo explícito: **apoyar la negociación de tarifas con aseguradores basada en costos reales**.

## 🖥️ Capa de Presentación: 6 portales (sección 5.3.6)
1. **Portal Ejecutivo** (directivos): KPIs, tendencias, semáforos, alertas, reportes PDF/Excel.
2. **Portal Operativo** (jefes de servicio): estado de salas en tiempo real, programación, variabilidad entre cirujanos, herramientas de reprogramación.
3. **Portal Individual** (cirujanos/anestesiólogos): indicadores propios con **benchmarking anónimo** y enfoque no punitivo.
4. **Portal de Costos** (administrativos/auditores): costo por componente, outliers, **rentabilidad = costo real vs. tarifa facturada**, tablas dinámicas, exportación.
5. **Portal de Comunidades de Práctica**: foros, repositorio, calendario, mensajería.
6. **Portal Público**: indicadores agregados de transparencia y rendición de cuentas.

### Tecnologías sugeridas por la tesis (secciones 5.3.6 y 5.8.1)
- **Frontend**: React, Vue.js o Angular; librerías D3.js / Chart.js / Plotly; **WCAG 2.1**; responsive.
- **Backend**: APIs RESTful; autenticación OAuth/SAML.
- **BD**: PostgreSQL, MySQL o SQL Server.
- **BI**: Power BI, Tableau, **Metabase o Apache Superset** (favorece código abierto); data warehouse + ETL.
- **Analítica avanzada**: Python (scikit-learn) o R.
- **Nube**: AWS/Azure/GCP para reducir inversión inicial; tablets para captura en sala.
- Software de costeo ABC/TDABC: **"desarrollo a medida"** ← aquí entra nuestro aplicativo.

## 📊 Sistema de indicadores (Componente 4, marco Donabedian)
Estructura–Proceso–Resultado. Los de resultado más ligados al aplicativo: costo promedio por cirugía y por procedimiento, **coeficiente de variación de costos**, desviación real vs. presupuesto, margen de contribución del servicio, tasa de glosas y de recaudo. Metas ejemplo: utilización de salas 65%→85%, costo cesárea $600.000→$510.000, infección sitio quirúrgico 3.5%→2.0%, lecciones aprendidas 2→10/mes. Actualización: operativos diarios/tiempo real, resultados mensuales/trimestrales, madurez anual.

## 🗓️ Plan, presupuesto e impacto
- **4 fases / 36 meses**: Fase 0 preparación (meses 1-3), Fase 1 fundamentos Nivel 3→4 (4-12), Fase 2 consolidación (13-24, aquí van **costeo ABC/TDABC $90M y plataforma BI $80M**), Fase 3 optimización Nivel 4→5 (25-36, analítica predictiva $60M).
- **Presupuesto total: $660M COP** (~$165k USD) para los 3 hospitales; financiación sugerida: 40% institucional, 40% regalías, 20% cooperación.
- **Reducción de costos por nivel CMM**: 5-8% a 12 meses → 10-12% a 24 → 15-18% a 36 → 18-20% aspiracional Nivel 5. El costeo ABC/TDABC aporta por sí solo **4-6%** y el registro de tiempos **3-5%**.
- Supuestos: 6.000 cirugías/año, $500.000/cirugía → ahorro acumulado $990M a 36 meses, **ROI 50%, recuperación ~18 meses**.
- Impacto departamental: +23% de capacidad quirúrgica (~1.380 cirugías extra/año), reducción de espera 20-30%.
- La **plataforma tecnológica es compartida entre los 3 hospitales** (economías de escala, red departamental de GC quirúrgica).

## Implicación para nuestro entendimiento
La versión completa confirma y amplía todo: el aplicativo web es el **software de costeo ABC/TDABC "a medida"** + su BI, con un **modelo de datos ya especificado por el profesor** (entidades de la sección 5.3.3) y un stack sugerido (web moderna + API REST + BD relacional + BI open source). Las complicaciones entran como variabilidad de costos y KPIs, no como motor predictivo (eso es meta Nivel 5). Ver [[Costeo ABC y TDABC]], [[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]].
