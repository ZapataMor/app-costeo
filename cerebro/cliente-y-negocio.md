---
tags: [cliente, negocio, proyecto]
migrado_de:
  - "información-contexto/Cerebro/01 - Explicaciones/Explicaciones.md"
  - "información-contexto/Cerebro/04 - Proyecto/Objetivo del Proyecto.md"
  - "información-contexto/Cerebro/04 - Proyecto/Orlando Ruiz.md"
  - "información-contexto/Cotizacion_App_Costeo_TDABC.docx"
actualizado: 2026-09-15
---

# Cliente y negocio

> Integra las notas *Explicaciones*, *Objetivo del Proyecto* y *Orlando Ruiz* del vault anterior, más la cotización de julio 2026. Nada se descartó; se reorganizó.

## 1. El cliente: Orlando Manuel Ruiz Pérez

Profesor y doctorando. Dueño de la visión del proyecto. Perfil reconstruido de su libro (2022).

- **Magíster en Finanzas**, especialista en Gerencia en Finanzas, **Ingeniero Industrial** (Uniguajira).
- Magíster en Sistemas Integrados de Gestión (U. de La Rioja, España); especialista en **Gerencia y Auditoría de la Calidad de la Salud** (U. Jorge Tadeo Lozano).
- Docente de pregrado y posgrado en Finanzas, Facultad de Ciencias Económicas y Administrativas, **Universidad de La Guajira**.
- **Fue gerente de la E.S.E. Hospital San José de Maicao** → conoce el problema desde adentro.
- Asesor de auditoría de calidad en salud en la Gobernación de La Guajira.
- Correo institucional: oruiz@uniguajira.edu.co

**Obra previa (la base del proyecto)**: libro *Costeo basado en actividad (ABC). Caso: E.S.E. Hospital San José de Maicao* (Uniguajira, 2022, ISBN 978-628-7619-28-9), con Jaider Genes Díaz y José Gregorio Sierra Llorente. Ver [[Fuente - Libro Costeo ABC Hospital San Jose de Maicao]].

**Doctorado**: tesis *"Sistema de Gestión del Conocimiento para la Optimización de los Costos del Servicio de Cirugía de los Hospitales de Mediana Complejidad del Departamento de La Guajira"*. Ver [[Fuente - Capitulo 5 Tesis Doctoral SGC]]. El aplicativo web es la **sistematización** de su modelo de costos, prevista desde el libro con estudiantes de ingeniería de sistemas de Uniguajira sede Maicao (semillero del profesor **Farit Pérez** — posible contacto clave).

Pendiente de conocer: universidad y programa doctoral exacto; fechas y entregables acordados formalmente.

Documentos recibidos del profesor (2026-07-05/06): libro ABC completo, capítulo 5 de la tesis (Word y luego PDF completo de 137 págs.), Excel de tablas de recolección de costos quirúrgicos, certificados editoriales. El 2026-09-06 llegó la guía de campos TDABC+MILP con la base de 3.100 cirugías simuladas. Resúmenes en `otros/fuentes/`.

## 2. Qué es realmente el proyecto

La tesis **no es un sistema general de facturación de pacientes**; es algo más específico:

1. **Dominio**: el servicio de **cirugía** de 3 hospitales públicos de La Guajira (Maicao, Riohacha y San Juan del Cesar).
2. **Problema**: los hospitales **no saben cuánto les cuesta realmente cada cirugía**. Negocian tarifas con las EPS a ciegas y a veces a pérdida (el libro del profesor demostró que el hospital de Maicao perdía hasta $94.310 por día de estancia en ciertas habitaciones sin saberlo).
3. **Solución de la tesis**: un [[Sistema de Gestion del Conocimiento (SGC)]] que capture datos quirúrgicos (tiempos, insumos, costos), los convierta en información (indicadores, dashboards) y en conocimiento (costos reales, variabilidad, mejores prácticas) para **reducir los costos quirúrgicos un 10-20 %**.
4. **El aplicativo web** es la materialización tecnológica de ese SGC — en particular la **plataforma de costeo ABC/TDABC** que hoy vive en hojas de Excel.

**La historia en una frase**: el profesor ya demostró en su libro (2022) que con [[Costeo ABC y TDABC|costeo ABC]] se puede calcular el costo real de un día de hospitalización en Excel. Su tesis doctoral escala esa idea al servicio de cirugía de 3 hospitales, envuelta en un sistema de gestión del conocimiento. **Nuestro trabajo es convertir ese modelo de Excel en un aplicativo web.**

## 3. Los tres conceptos que hay que dominar

**a) Costeo TDABC — "el costo por minuto"**. Cada cirugía cuesta la suma de sus recursos por el tiempo que se usan: `Costo = Σ(costo por minuto del recurso × minutos de uso) + insumos consumidos`. El costo/minuto del cirujano sale de su salario mensual ÷ minutos disponibles; el del quirófano, de depreciación + servicios + mantenimiento ÷ minutos disponibles (12 h/día × 26 días). Ver [[Costeo ABC y TDABC]].

**b) El instrumento de captura**. El Excel del profesor ([[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]]) define QUÉ datos se capturan por cirugía: equipo humano y sus tiempos, medicamentos, insumos, esterilización, ropa quirúrgica, quirófano/minuto, depreciación de equipos, personal indirecto y logística. **Ese Excel es, en la práctica, el modelo de datos del aplicativo.**

**c) Costo vs. tarifa = sobrevivir**. El hospital cobra a las EPS con tarifas pactadas (en Maicao: **SOAT menos 25 %**). Si el costo real supera la tarifa, el hospital pierde en silencio. El aplicativo hace visible ese margen por procedimiento — esa es su razón de ser financiera. Ver [[Sistema de Salud Colombiano]] y [[Cuenta del Paciente]].

## 4. Complicaciones y escalamiento de costos

La intuición inicial (paciente que se complica → cuenta que se dispara) sigue siendo válida pero se reubica: en la tesis las complicaciones aparecen como **variabilidad de costos** (¿por qué la misma cirugía costó el doble?) y como **conocimiento a capturar** (lecciones aprendidas, tasas de complicación como KPI). La capa 4 del SGC contempla *modelos predictivos de costos* como meta avanzada (Nivel 5 de madurez), no como el punto de partida. Ver [[Complicaciones y Escalamiento de Costos]].

## 5. Datos duros para tener a mano

| Dato | Valor |
|---|---|
| Hospitales objetivo | HSJ Maicao · HNR Riohacha · HSR San Juan del Cesar |
| Madurez actual (CMM-GC, 1-5) | 2.755 · 3.149 · 3.429 (los tres = Nivel 3) |
| Dimensión más débil | "Aplicación en Costos" en los 3 hospitales |
| Factor de ineficiencia institucional (pipeline TDABC+MILP) | 1,28 · 1,20 · 1,15 |
| Procedimientos piloto TDABC | Cesáreas, apendicectomías, herniorrafias, colecistectomías, partos, legrados |
| Costo día de estancia HSJ (2019, libro) | General $153.027 · Pediatría $148.690 · Ginecología $215.810 |
| Caso de prueba del motor | Cesárea por TDABC = **$520.000 COP** (capítulo 5) |
| Tasas del pipeline doctoral | Cirujano 1.550 COP/min · Quirófano 3.800 · CIF 420 COP/min ([[Fuente - Guia de Campos Base de Datos TDABC+MILP]]) |
| Meta de reducción de costos | 10-15 % (Nivel 4) → 15-20 % (Nivel 5) |
| Referente de software citado | OptiSurge (Colombia): utilización de salas de 61 % → 75 % en 10 días |
| Estándares obligatorios | CUPS (procedimientos), CIE-10 (diagnósticos), ATC (medicamentos), Ley 1581/2012 (datos personales) |
| Presupuesto de la tesis | $660M COP / 36 meses para los 3 hospitales (costeo ABC/TDABC $90M + BI $80M en Fase 2) |

## 6. Módulos que la tesis pide (Fase C de TOGAF) y lo que la tesis ya resolvió

1. **Gestión de hospitales/clínicas** (registro configurable de cada ente: salas, personal, tarifas, procedimientos, tecnologías).
2. **Registro de tiempos quirúrgicos** por cirugía, rol y sala (referente: OptiSurge; la tesis pide app en **tablet con modo offline** para la sala).
3. **Captura de consumos** (medicamentos, insumos, esterilización, ropa quirúrgica) — digitaliza el Excel de tablas.
4. **Motor de costeo ABC/TDABC**: parámetros de costo/minuto por recurso × tiempos + insumos = costo real por procedimiento.
5. **Dashboards/BI**: costo promedio y variabilidad por procedimiento/cirujano/período, utilización de salas, margen costo vs. tarifa, KPIs Donabedian.
6. **Capas del SGC**: repositorio de conocimiento, lecciones aprendidas y lo necesario para la vista integral del paciente.

Lo que la versión completa del capítulo 5 ya nos dio resuelto (2026-07-06):
- **Modelo de datos relacional** (sección 5.3.3): Paciente, Procedimiento (CUPS), Cirugía, Equipo Quirúrgico (tiempos por rol), Insumo, Consumo, Equipo Médico, Sala Operatoria, Costo de Cirugía y Resultado Clínico. Es prácticamente el diagrama entidad-relación del aplicativo — y el que está implementado.
- **Stack sugerido**: React/Vue/Angular + API REST + PostgreSQL/MySQL + BI open source (Metabase/Superset) + nube. El software de costeo lo describe como **"desarrollo a medida"** — ese desarrollo somos nosotros.
- **6 portales por usuario**: ejecutivo, operativo (tiempo real de salas), individual del cirujano (benchmarking anónimo, no punitivo), de costos (margen costo vs. tarifa), comunidades de práctica y público.
- **Validaciones exigidas**: completitud, consistencia, integridad referencial y detección de outliers.

## 7. Alcance confirmado por el usuario (2026-07-06) ✅

El usuario es el **desarrollador contratado del aplicativo completo** (no pasantía ni semillero).

| Decisión | Respuesta |
|---|---|
| Alcance | **Costeo + capas del SGC** (vista integral del paciente): ambas cosas |
| Modelo | **Multi-hospital genérico**: cualquier hospital/clínica se registra con sus propios procedimientos, tecnologías y características |
| Futuro | Módulo para **compartir información entre entes hospitalarios** (red departamental de GC) |
| Integración | Sí — comunicarse con los sistemas existentes de cada hospital (HCE, facturación) |
| Stack | **Laravel + MySQL + React** (stack del usuario) — ver [[2026-07-06-stack-laravel-inertia-react]] |
| Parámetros de costos | Los suministra y administra **cada hospital** (salarios, costo/minuto de sala, insumos) |
| Datos | **Reales**, brindados por los hospitales; Habeas Data (Ley 1581/2012) se organiza con ellos |
| Entregable | **Prototipo funcional + mediciones reales durante algunos meses** |

Hito previsto entonces: reunión con directivos del **Hospital San José de Maicao (~7-8 de julio de 2026)** para conocer sus **10 procedimientos principales**. Luego se preparó un cuestionario de 50 preguntas para una visita institucional el **2026-07-22** ([[preguntas-levantamiento-hospital]]). [VERIFICAR] resultados de ambas reuniones: no quedaron registrados.

**Restricciones y estándares**: CUPS y CIE-10; interoperabilidad (HL7) como meta; protección de datos personales; sostenibilidad (preferir código abierto y bajo costo de operación — principio 7 de la tesis); contexto real difícil: conectividad inestable, equipos limitados, alta rotación de personal.

## 8. Lo que aprendimos de la literatura externa (2026-07-11)

Seis documentos (no del profesor) y cuatro lecciones:

**a) La matemática fina del TDABC** ([[Fuente - Ebook TDABC (Kaplan y Anderson)]]): el costo/minuto se calcula sobre **capacidad práctica** (~80 % jornada en personas, ~85 % en máquinas); las variantes de una cirugía (complicación, conversión, reintervención) se modelan como **ecuaciones de tiempo** (términos aditivos); el costo de la **capacidad no utilizada** queda visible como reporte propio.

**b) Los casos reales confirman el patrón**: [[Fuente - Caso ABC Hospital Regional de Talca (Chile)|Talca]], [[Fuente - Tesis ABC Quirofano Hospital San Juan de Dios (Cuenca)|San Juan de Dios]], [[Fuente - Tesis Gestion Costos Cirugias Hospital del Nino y la Mujer (Cuenca)|Hospital del Niño y la Mujer]], [[Fuente - Tesis ABC Quirofano Hospital Leon Becerra (Guayaquil)|León Becerra]]: hospitales que no conocen sus costos, **25-40 % del costo escondido en actividades de apoyo**, mano de obra dominante (>75 %), y la recomendación explícita de que la solución es **un software integrado**. Sus cirugías representativas (cesárea, colecistectomía, apendicectomía) coinciden con nuestros pilotos.

**c) Dos indicadores candidatos**: **tiempos de espera del paciente** (Talca: ~14 h perdidas por paciente) y **punto de equilibrio quirúrgico** (mínimo 27 cirugías/mes según mezcla). Ambos están en el [[backlog-mejoras]].

**d) Requisitos probados para insumos/consumos** ([[Fuente - Tesis Herramienta Digital Compras Biomedicas HAMA (Medellin)]]): catálogo maestro con buscador y autocompletado, estados calculados, listas parametrizables, dashboard por servicio/proveedor/mes; fórmula de adopción: importar histórico + instructivo + video.

Lo que estos documentos NO cubren: tarifas SOAT / sistema colombiano ni la capa SGC — ahí seguimos dependiendo del capítulo 5.

## 9. Qué dice el pipeline doctoral (2026-09-06)

La guía TDABC+MILP ([[Fuente - Guia de Campos Base de Datos TDABC+MILP]]) fija que el **CIF se asigna por minuto de quirófano (420 COP/min)**, no como porcentaje del costo directo; que la capacidad práctica es el 80 %; que hay sobrecosto del 50 % por horas extra después de las 15:00; y una clasificación de desviación (`Subejecucion` ≤ −5 % · `En_Rango` · `Sobrecosto_Leve` 5-20 % · `Sobrecosto_Alto` > 20 %). Tras auditar el aplicativo se implementó el **motor de bolsas CIF** ([[Costos Indirectos (CIF)]]). Los datos del pipeline son **simulados**, no observaciones de campo. La *Fundamentación* ([[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]]) explica el porqué: la tesis añadió un **tercer objetivo** — base de datos de 3.000+ cirugías reales + optimización TDABC-MILP con ML — y define 20 indicadores con meta (utilización 75-85 %, turnover 25-35 min, brecha costo/tarifa documentada, completitud > 90 %). El aplicativo es la fuente natural de esa base de datos real; el Excel de 3.100 cirugías simuladas ([[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]]) es su prototipo. Ojo: el pipeline usa tasas casi el doble de las del ejemplo de la cesárea de $520.000 — dos parametrizaciones del mismo profesor a aclarar ([[preguntas-abiertas]]).

## 10. Acuerdo comercial

Fuente: `información-contexto/Cotizacion_App_Costeo_TDABC.docx` (julio 2026, validez 30 días). [VERIFICAR] si fue aceptada y firmada.

- **Objeto**: desarrollo completo del aplicativo (capas 1-5 de Kerschberg), funcional y escalable, **en el marco de una asociación para su futura comercialización** en otros centros de salud.
- **Valor**: **COP $9.000.000** (referencia de mercado citada: $40-70 M; esfuerzo estimado 400-500 h, ~$20.000/h). Ofrecido como "precio de socio".
- **Forma de pago**: 50 % anticipo a la aceptación (contra demo de capas 1-3) · 25 % al completar capa 4 (reglas, alertas, recomendaciones) · 25 % con capa 5 en producción y capacitación hecha.
- **No incluye**: soporte/hosting/mantenimiento mensual (se cotiza aparte), implantación en hospitales adicionales, funcionalidades fuera del alcance.
- **Acuerdo de asociación**: se recomienda formalizar por escrito, antes de la primera venta a terceros, los porcentajes de participación y la titularidad compartida de la PI.
- Estado declarado en la cotización: capas 1-3 "desarrolladas y funcionales" (~22.000 líneas, 117 pruebas entonces; hoy 299); capas 4 y 5 "por desarrollar".

## 11. Confidencialidad ⚠️

El repositorio `ZapataMor/app-costeo` es **público en GitHub** (verificado 2026-09-15). Ya están versionados: este cerebro, el correo del profesor, cifras financieras del Hospital San José de Maicao (del libro publicado), la cotización comercial con su valor y forma de pago, y los PDFs/Word originales de la tesis (capítulo 5 inédito, *Fundamentación*, guía y base de datos del pipeline) en `cerebro/otros/fuentes/originales/` (las carpetas `Documentos contexto SICOPH/` e `información-contexto/` se eliminaron el 2026-09-15, pero siguen en el historial de Git). Decisión pendiente del usuario: hacer privado el repo o sacar del historial el material sensible. Ver [[2026-09-15-cerebro-en-el-repo]].

## 12. Lo que aún no sabemos

Ver [[preguntas-abiertas]].
