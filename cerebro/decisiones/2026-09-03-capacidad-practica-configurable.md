---
tags: [decision, costeo]
fecha: 2026-09-03
estado: vigente
---

# Capacidad práctica configurable: `minutos_efectivos_hora`

## Contexto
Kaplan & Anderson: el costo/minuto se calcula sobre **capacidad práctica** (~80 % de la jornada en personas, ~85 % en máquinas), no la teórica ([[Fuente - Ebook TDABC (Kaplan y Anderson)]]). El MILP de la tesis usa `T_disp = 80 % de 480 min`. El motor asumía 60 minutos productivos por hora (12 h × 26 días × 60 = 18.720 min/mes).

## Decisión
Columna `hospitales.minutos_efectivos_hora` (default **60** para no alterar lo existente; 48 = 80 %). Entra en `Hospital::minutosDisponiblesMes()` y en las capacidades sumadas; se congela por cirugía en `minutos_efectivos_hora_registrado`. La tarjeta de parámetros muestra la capacidad efectiva resultante.

## Por qué
- Un parámetro, todo el motor: sin él las tasas por minuto quedan subestimadas un 20 %.
- Default conservador: la cesárea de $520.000 sigue dando $520.000 ([[2026-07-06-cesarea-520000-como-caso-de-prueba]]).

## Consecuencias
- El hospital debe fijarlo conscientemente al ponerse en marcha (paso 1 de `docs/cif-implementacion.md` §8).
- Con 40 la capacidad baja a 12.480 y toda tarifa por minuto sube 50 %: los KPIs de utilización usan la vigente ([[2026-07-13-snapshot-de-parametros-en-la-cirugia]]).
