---
tags: [moc, agentes]
---

# Agentes

Roles expertos que analizan el proyecto desde un ángulo. Cuando el usuario dice **"analiza con los agentes"**, Claude lee cada nota de esta carpeta, analiza el proyecto desde ese rol y agrega las propuestas al [[backlog-mejoras]] (ver `CLAUDE.md`).

| Agente | Ángulo |
|---|---|
| [[arquitecto]] | Estructura del código, modelo de datos, mantenibilidad, rendimiento, deuda técnica |
| [[qa-seguridad]] | Tests, aislamiento multi-tenant, datos personales, integridad de cálculos, CI |
| [[ux-ui]] | Flujo del digitador en sala, legibilidad de los tableros, accesibilidad, fricción |
| [[analista-negocio]] | Valor para el cliente, alineación con la tesis y la cotización, alcance, adopción |
| [[experto-procesos-hospitalarios]] | Cómo funciona de verdad un quirófano y un hospital público colombiano |
| [[experto-contabilidad-costos]] | Corrección contable del ABC/TDABC, CIF, capacidad, márgenes, normativa de costos |
| [[experto-procedimientos-medicos]] | Realidad clínica de los procedimientos: fases, variantes, complicaciones, codificación |

Todos usan el mismo formato de salida: **hallazgo → impacto → mejora propuesta → prioridad**.
