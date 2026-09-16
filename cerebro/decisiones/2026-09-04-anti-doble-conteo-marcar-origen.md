---
tags: [decision, costeo, cif, integridad]
fecha: 2026-09-04
estado: vigente
---

# Anti-doble-conteo: marcar el origen del componente, no ponerlo a cero

## Contexto
Tres campos del catálogo ya contienen indirectos por dentro: `salas_operatorias.costo_hora` (servicios, mantenimiento, depreciación del inmueble), `equipos_medicos.costo_hora` (depreciación) y `recursos_humanos.costos_indirectos_mensuales`. Activar una bolsa de la categoría equivalente contaría el mismo costo dos veces, en silencio.

## Decisión
**Invariante**: un componente nunca está contado por el catálogo directo y por una bolsa a la vez, ni desaparece de ambos.
- Columnas `hospitales.origen_infraestructura | origen_depreciacion_equipos | origen_personal_indirecto` con enum `OrigenComponente` (`digitado` | `derivado_de_cif`). El componente marcado `derivado_de_cif` **se omite del costo directo**; el dato digitado permanece.
- `activo` y `origen_*` están **fuera del `$fillable`**; solo `ActivarCategoriaCif` / `DesactivarCategoriaCif` los escriben, **en una transacción**. No existe estado intermedio observable.
- Activar exige **confirmación explícita** si el componente sigue digitado con valor > 0, y el diálogo nombra los registros con su valor. Cero no bloquea (sala prestada, equipo donado).
- Un concepto activo no se puede borrar ni cambiarle monto, porcentaje, base, categoría o inicio de vigencia; la forma de cambiar el precio es cerrar la vigencia y abrir otra. Sin vigencias solapadas (`SinSolapeDeVigencias`).
- En el motor, un componente solo cuenta como `derivado_de_cif` si además **existe una bolsa vigente** de esa categoría: una bolsa que vence devuelve el componente al directo.
- La exclusión de indirectos del personal viaja congelada en `costo_mensual_registrado` (`RecursoHumano::costoMensualTotal(false)`), para que el SQL de `PersonalCosteoService` quede alineado.

## Por qué (marcar y no poner a cero)
1. **No destructivo**: el dato digitado costó recolectar y la tesis lo necesita para comparar métodos.
2. **Auditable**: `fuente` y `nivel_confiabilidad` siguen siendo verdaderos sobre un valor real; un cero forzado los convierte en mentira.
3. **Un solo lugar de verdad**: el invariante es por hospital y categoría, no por fila (con ceros por fila, una sala en cero y otra no reabren el doble conteo).
4. Un `costo_hora = 0` legítimo y uno forzado serían indistinguibles.

## Consecuencias
- El hospital debe pasar por la activación explícita (paso 3 de `docs/cif-implementacion.md` §8).
- Tests que fijan el invariante: `ActivarCategoriaCifTest` (12), `ConceptoCostoIndirectoTest` (16), `MotorCifTest` (14).
