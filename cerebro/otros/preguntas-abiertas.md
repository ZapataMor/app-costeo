---
tags: [proyecto, preguntas]
migrado_de: "información-contexto/Cerebro/04 - Proyecto/Preguntas Abiertas.md"
---

# ❓ Preguntas Abiertas

Dudas para resolver con el profesor [[cliente-y-negocio|Orlando Ruiz]] o con los hospitales. **Actualizado 2026-09-15** al migrar el cerebro: se añadieron las preguntas del módulo CIF y los [VERIFICAR] del nuevo [[00-resumen]]. El cuestionario completo de 50 preguntas para la visita institucional está en [[preguntas-levantamiento-hospital]].

## Pendientes nuevas (2026-09-06 → 2026-09-15)
- [ ] **Bolsas CIF**: ¿de dónde saldrá el monto mensual de cada bolsa en cada hospital — contabilidad, facturas, presupuesto? (ver [[Costos Indirectos (CIF)]])
- [ ] ¿Se acepta que activar o desactivar una sala cambie las tasas por minuto futuras de todo el hospital? (denominador = capacidad sumada, [[2026-09-06-denominador-capacidad-sumada]])
- [ ] ¿El **factor de ineficiencia institucional** del pipeline (1,15–1,28) debe modelarse en el aplicativo o es solo del análisis de la tesis?
- [ ] ¿Se modelan las **horas extra** (sobrecosto 50 % después de las 15:00, campo `tdabc_overtime_cop` del pipeline)?
- [ ] ¿Alineamos los umbrales de la alerta de sobrecosto con la clasificación del pipeline (−5 / 5 / 20 %)?
- [ ] **Doble parametrización**: la cesárea del capítulo 5 cuesta $520.000 (cirujano $50.000/h) y la del pipeline ~$1.100.000 (cirujano 1.550 COP/min ≈ $93.000/h). ¿Cuál juego de tasas es la referencia para los hospitales? ([[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]])
- [ ] ¿Existe una versión completa de la *Fundamentación* con las secciones 5.4 y 5.5 (TDABC y modelo de optimización)? En la recibida están vacías.
- [ ] ¿Cuáles de los campos clínicos del segundo modelo de datos (ASA, comorbilidades, servicios de apoyo) deben capturarse en el aplicativo para la base de 3.000 cirugías reales? ([[backlog-mejoras]] #20)
- [ ] ¿Qué pasó en la reunión de Maicao (~2026-07-08) y en la visita del 2026-07-22? ¿Cuáles son los 10 procedimientos principales? ¿Qué HIS usan?
- [ ] ¿La cotización de julio 2026 fue aceptada? ¿Se recibió el anticipo? ([[cliente-y-negocio#10. Acuerdo comercial]])
- [ ] ¿Se hace privado el repositorio de GitHub? ([[cliente-y-negocio#11. Confidencialidad ⚠️]])

## Pendientes desde 2026-07-06

- [ ] **Los 10 procedimientos principales por hospital**: se conocerán en la **reunión con los directivos del Hospital San José de Maicao (programada para ~2026-07-07/08)**. Cada hospital visitado dirá sus 10 procedimientos principales (pueden repetirse entre hospitales).
- [ ] **Detalles técnicos de la integración** con los sistemas existentes (¿qué HIS/HCE usa cada hospital? ¿hay API, HL7, o acceso a BD?). Se confirmará hospital por hospital.
- [ ] **Definición fina del stack y arquitectura**: se trabajará junto con Claude sobre la base ya fijada (Laravel + MySQL + React).
- [ ] **Habeas Data**: los detalles operativos (acuerdos, anonimización, roles de acceso) se organizarán con cada hospital.
- [ ] ¿Cuántos meses de **medición con datos reales** exige el entregable y en cuáles hospitales se medirá?

## ✅ Respondidas por el usuario (2026-07-06)
- ~~¿Solo plataforma de costeo o también capas del SGC?~~ → **Ambas cosas**: el aplicativo incluye las capas del SGC necesarias para conocer todo lo relacionado con los pacientes, además del costeo.
- ~~¿Multi-hospital o piloto único?~~ → **Multi-hospital y genérico/configurable**: debe poder registrarse **cualquier hospital o clínica** con sus propias características, procedimientos y tecnologías (unos tienen cosas que otros no). A futuro: módulo para **compartir información entre entes hospitalarios** dentro de la app.
- ~~¿Integración con sistemas existentes o captura manual?~~ → **Se busca la integración** con los aplicativos existentes de los hospitales; esa información es valiosa.
- ~~¿Entregable de la tesis?~~ → **Prototipo funcional + datos reales**, con mediciones hechas con el aplicativo **durante algunos meses** de operación.
- ~~¿Tecnologías?~~ → El usuario trabaja con **Laravel, MySQL y React**; la definición fina se hará después junto con Claude.
- ~~¿Quién administra los parámetros de costos?~~ → **Cada hospital suministra sus propios datos** (salarios, costo/minuto de sala, etc.).
- ~~¿Datos personales (Ley 1581/2012)?~~ → Se trabajará con **datos reales brindados por el hospital**; el tema de **Habeas Data se organizará con ellos**.
- ~~¿Rol del usuario?~~ → **Desarrollador contratado del aplicativo completo** (no pasantía ni semillero: es un contrato de desarrollo).

## ✅ Respondidas por los documentos (2026-07-05)
- ~~¿Cuál es la hipótesis doctoral?~~ → Un SGC contextualizado eleva la madurez CMM-GC y eso optimiza los costos quirúrgicos 10-20%. ([[Fuente - Capitulo 5 Tesis Doctoral SGC]])
- ~~¿El foco es contable, financiero o predictivo?~~ → **Contable-gerencial** (costeo ABC/TDABC + indicadores). Lo predictivo es meta de madurez Nivel 5.
- ~~¿Con qué tarifario se cotiza?~~ → Referencia **SOAT** (Decreto 2423/1996); en la práctica el HSJ contrata a SOAT –25%. El costo ABC se compara contra esas tarifas.
- ~~¿Se usan CUPS y CIE-10?~~ → Sí, explícito en la capa de datos de la tesis.
- ~~¿De dónde salen los costos reales?~~ → Se construyen con el modelo ABC/TDABC; el instrumento es el Excel de tablas.
- ~~¿Es específico para Colombia?~~ → Sí: 3 hospitales públicos de La Guajira (HSJ, HNR, HSR) como punto de partida — pero el aplicativo debe ser genérico para cualquier hospital/clínica.
- ~~¿Quiénes son los usuarios?~~ → Por rol: gerencia (dashboards ejecutivos), jefes de cirugía (paneles operativos), cirujanos (reportes individuales), personal administrativo/costos.
- ~~¿Predice complicaciones o simula escenarios?~~ → Ninguna de las dos como núcleo: **mide costos reales y su variabilidad**; las complicaciones son KPIs y fuente de variabilidad/lecciones aprendidas.
