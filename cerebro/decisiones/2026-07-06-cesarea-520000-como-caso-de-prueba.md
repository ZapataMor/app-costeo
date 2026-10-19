---
tags: [decision, costeo, testing]
fecha: 2026-07-06
estado: vigente
---

# La cesárea de $520.000 del capítulo 5 es el caso de prueba obligatorio del motor

## Contexto
El capítulo 5 de la tesis trae un ejemplo numérico verificable: cirujano $50.000/h × 1,5 h + ayudante $30.000 × 1,5 + anestesiólogo $50.000 × 2 + instrumentador $20.000 × 2 + circulante $15.000 × 2 + sala $40.000 × 2 + insumos $150.000 = **$520.000 COP** ([[Fuente - Capitulo 5 Tesis Doctoral SGC]]).

## Decisión
- `Unit/TdabcCostingServiceTest` reproduce ese cálculo exacto y **falla si el motor deja de darlo**.
- `DemoSeeder` siembra esa cesárea en el hospital de demostración.

## Por qué
- Es el único número del profesor que se puede reproducir de punta a punta; ancla el motor a la tesis.
- Cualquier cambio en la fórmula (capacidad práctica, CIF por bolsas) debe seguir dando $520.000 con los parámetros del ejemplo → protege contra regresiones semánticas, no solo técnicas.

## Consecuencias
- Nuevos parámetros del motor deben tener **defaults que reproduzcan el comportamiento anterior** (p. ej. `minutos_efectivos_hora = 60`, sin bolsas activas ⇒ vía `factor`).
