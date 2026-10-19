---
tags: [decision, costeo, datos]
fecha: 2026-07-13
estado: vigente
---

# Cada cirugía congela los parámetros con los que se costea

## Contexto
Los parámetros de costo (salarios, costo/hora de sala y equipos, costo unitario de insumos, tarifas, capacidad) cambian con el tiempo. Recostear una cirugía del año pasado con los parámetros de hoy produciría un número distinto al que se reportó entonces.

## Decisión
Al registrar una cirugía (`RegistrarCirugia`) se copian a la cirugía y sus pivotes los valores vigentes: `costo_mensual_registrado` por miembro del equipo, `costo_hora_sala_registrado`, `costo_hora_registrado` por equipo, costo unitario de cada consumo, tarifas del procedimiento, `minutos_efectivos_hora_registrado` y, desde 2026-09-06, `parametros_cif_registrados` (json con vía, orígenes, denominadores y bolsas). El motor **lee la foto, no el catálogo**. Migración `2026_07_13_100000_snapshots_tarifas_estado_en_proceso_y_hmac` y siguientes.

## Por qué
- Reproducibilidad: el costo de una cirugía es un hecho histórico, no una función del catálogo actual.
- Es lo que hace defendible la comparación "método tradicional vs. método por bolsas" de la tesis: se compara **entre periodos**, sin reprocesar el pasado.
- Auditable: la ficha de costo se puede rehacer a mano desde lo congelado.

## Consecuencias
- Cambiar un parámetro **no** corrige cirugías ya registradas; si el dato estaba mal, hay que corregir la cirugía.
- Los **indicadores de utilización** usan la capacidad vigente, no la congelada (cambiar `horas_dia` reescribe porcentajes históricos de ocupación). Documentado en el README; candidato a revisión por [[experto-contabilidad-costos]].
