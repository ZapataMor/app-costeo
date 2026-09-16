---
tags: [decision, costeo, cif]
fecha: 2026-09-06
estado: vigente
---

# El denominador de una bolsa por minuto es la capacidad sumada del hospital, no la de un recurso

## Contexto
`Hospital::minutosDisponiblesMes()` (18.720) es capacidad **por recurso individual** — correcta como denominador del costo/minuto de una persona. Una bolsa mensual (la energía del hospital) cubre **todos** los quirófanos: dividirla entre la capacidad de uno solo multiplica la tasa por N.

## Decisión
- `minuto_quirofano`: denominador = `salas activas × horas_dia × dias_mes × minutos_efectivos_hora` (`Hospital::capacidadQuirofanosMes()`).
- `minuto_personal`: denominador = `personal quirúrgico activo × misma capacidad` (`capacidadPersonalQuirurgicoMes()`).
- Activar una bolsa por minuto **sin capacidad activa** lanza `CapacidadCifNoDisponibleException` con el arreglo nombrado; nunca se divide por cero.
- El denominador se congela por cirugía en `parametros_cif_registrados`.

## Por qué
- Coincide con la tesis (420 COP/min es una tasa del hospital) y con `KpiService::utilizacionSalas()`, que ya suma la capacidad de cada sala.
- `MotorCifTest::test_la_tasa_baja_cuando_el_hospital_tiene_mas_salas` fija la diferencia.

## Consecuencias
- Activar o desactivar una sala cambia las tasas por minuto **futuras** de todo el hospital. Pendiente de validar con el profesor ([[preguntas-abiertas]]).
