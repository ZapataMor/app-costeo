---
tags: [decision, costeo, cif]
fecha: 2026-09-04
estado: vigente
---

# Costo indirecto por bolsas con inductor propio; el factor plano y las bolsas son vías excluyentes

## Contexto
El motor asignaba el CIF como `costo_directo × hospitales.factor_indirecto` (costeo tradicional). La guía del pipeline doctoral asigna el CIF **por minuto de quirófano (420 COP/min)**, y el Excel del profesor trae cuatro bolsas con inductor propio. Sobre las 3.100 cirugías simuladas, un factor único del 3,69 % desvía el CIF asignado **6,8 % en promedio y hasta 26,3 %** en un procedimiento. Detalle en [[Costos Indirectos (CIF)]] y `docs/cif-vacios-diseno.md`.

## Decisión
- Catálogo `conceptos_costo_indirecto` (bolsas) por hospital: categoría (`infraestructura`, `depreciacion_equipos`, `personal_indirecto`, `administracion`, `servicios_generales`), base de asignación (**lista cerrada**: `minuto_quirofano`, `minuto_personal`, `porcentaje_directo`), monto mensual o porcentaje, vigencia, `fuente` y `nivel_confiabilidad`.
- Dos vías **excluyentes**: `factor` cuando no hay bolsas activas y vigentes; `bolsas` cuando las hay — y entonces **el `factor_indirecto` se ignora**. La pantalla del hospital lo advierte.
- Cada bolsa aplicada deja una línea auditable en `cirugia_concepto_indirecto` (monto mensual, denominador, tasa, unidades, monto asignado).

## Por qué
- Es la razón de ser del ABC: sustituir el prorrateo arbitrario por inductores que reflejen el consumo real.
- Vías excluyentes evitan sumar dos veces el mismo indirecto y hacen comparable "método actual vs. método por bolsas" entre periodos.
- Lista cerrada de bases: cada una tiene un denominador definido y probado; abrirla invitaría a inductores sin capacidad definida.

## Consecuencias
- Las bolsas solo aplican a cirugías registradas **después** de activarlas ([[2026-07-13-snapshot-de-parametros-en-la-cirugia]]).
- Requiere [[2026-09-04-anti-doble-conteo-marcar-origen]] y [[2026-09-06-denominador-capacidad-sumada]].
- Preguntas abiertas para el profesor: de dónde sale el monto mensual de cada bolsa ([[preguntas-abiertas]]).
