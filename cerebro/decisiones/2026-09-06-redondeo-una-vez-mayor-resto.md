---
tags: [decision, costeo, precision]
fecha: 2026-09-06
estado: vigente
---

# Redondear una sola vez; cuadrar líneas por mayor resto

## Contexto
Con varias bolsas y varias fases, redondear cada línea a centavos puede desviar el total tanto como líneas haya; las tres sumas (bolsas, fases, total) deben cuadrar al centavo para que la ficha de costo sea auditable a mano.

## Decisión
Cada bolsa se calcula a precisión completa; el total se redondea **una vez**; las líneas se cuadran contra ese total por **mayor resto** (`AsignadorCif::repartirPorMayorResto()`). El mismo mecanismo prorratea el indirecto entre las fases del ciclo (pesos = participación de cada fase en el costo directo).

## Por qué
- `Σ cirugia_concepto_indirecto.monto_asignado == costos_cirugia.costo_indirecto` y `Σ detalle.indirecto_por_fase == costo_indirecto`, con tests que lo fijan.
- Un contador puede rehacer la cuenta: «$28.080.000 ÷ 56.160 min = $500/min × 120 min = $60.000».

## Consecuencias
- Cualquier nuevo reparto proporcional en el motor debe reutilizar `repartirPorMayorResto`, no redondear por línea.
