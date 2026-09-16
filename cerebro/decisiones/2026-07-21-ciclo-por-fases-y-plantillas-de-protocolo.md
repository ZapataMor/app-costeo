---
tags: [decision, datos, ux, costeo]
fecha: 2026-07-21
estado: vigente
---

# Ciclo quirúrgico por fases (pre → quirúrgica → post) y plantillas de protocolo por procedimiento

## Contexto
El TDABC de la tesis usa **ecuaciones de tiempo** por recurso: no todos ocupan el mismo tiempo que dura la cirugía (15 min pre + 10 min post; cirujano +12,5; instrumentadora +15; auxiliar 60 % …). Capturar solo `hora_inicio`/`hora_fin` perdía esa estructura, y arrancar cada registro de cero era el trabajo que la app existe para ahorrar.

## Decisión
- Marcas de tiempo en la cirugía: entrada del paciente, incisión, cierre, salida de recuperación; estados `programada → en_proceso → en_recuperacion → realizada | cancelada` (`EstadoCirugia`). Solo una cirugía **realizada** se costea (`CirugiaNoCosteableException`).
- Enum `FaseCiclo` (`pre`, `quirurgica`, `post`): personal, consumos y equipos se atribuyen a una fase; el costo se reporta por fase y el indirecto se prorratea entre fases por su peso en el directo.
- Horas de entrada/salida por miembro del equipo → minutos de participación derivados, no digitados.
- **Plantillas de protocolo** por procedimiento (`PlantillaPersonal`, `PlantillaInsumo`, `PlantillaEquipo`, tiempos por fase): cada registro nace prellenado; `GeneradorPlantilla` la sugiere desde el histórico.

## Por qué
- Refleja la ecuación de tiempo del pipeline doctoral y permite medir esperas y alistamiento ([[backlog-mejoras]] #8).
- Menos digitación y menos error en sala; el protocolo se vuelve conocimiento explícito (capa 4 del SGC).

## Consecuencias
- Aún falta modelar términos condicionales (complicación, conversión) — [[backlog-mejoras]] #6.
