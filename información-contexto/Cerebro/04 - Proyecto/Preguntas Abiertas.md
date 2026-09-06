---
tags: [proyecto, preguntas]
---

# ❓ Preguntas Abiertas

Dudas para resolver con el profesor [[Orlando Ruiz]]. **Actualizado el 2026-07-06: el usuario respondió la tanda de preguntas del alcance** — ver respuestas abajo. Quedan pocas pendientes.

## Pendientes ahora
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
