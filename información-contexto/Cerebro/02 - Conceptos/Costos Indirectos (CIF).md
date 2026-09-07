---
tags: [concepto, nucleo-del-proyecto]
---

# Costos Indirectos de Fabricación (CIF)

**El problema más difícil del costeo quirúrgico y el que separa el ABC del costeo tradicional.** Ver [[Costeo ABC y TDABC]].

## Qué son

Todo lo que el quirófano consume pero **no se puede rastrear directamente a una cirugía concreta**: energía eléctrica, gases medicinales, agua, aseo, mantenimiento, seguros, depreciación del inmueble, lavandería, esterilización, residuos hospitalarios, y la nómina que no entra a la sala (jefe de enfermería, coordinador quirúrgico, camilleros, facturación).

El bisturí se sabe de qué cirugía fue. El recibo de la luz, no.

## Las dos formas de repartirlos

| | Costeo tradicional | ABC / TDABC |
|---|---|---|
| Mecanismo | Un **porcentaje único** sobre el costo directo | Cada bolsa se reparte por su **propio inductor** |
| Ejemplo | "los indirectos son el 12 % de lo directo" | "la energía se reparte por minuto de quirófano" |
| Problema | Prorrateo arbitrario: quien gasta más insumos carga más luz | Requiere identificar el inductor de cada bolsa |

**La razón de ser del ABC es exactamente esto:** sustituir el prorrateo arbitrario por inductores que reflejen el consumo real. Un porcentaje plano castiga a los procedimientos caros en material y perdona a los procedimientos largos, que son los que de verdad consumen quirófano.

### Cuánto importa el error (medido, 2026-09-06)

Sobre la base de 3.100 cirugías del pipeline doctoral ([[Fuente - Guia de Campos Base de Datos TDABC+MILP]]), la razón CIF ÷ costo directo va del **2,92 % al 4,17 %** según el tipo de cirugía y el hospital. Sustituir eso por un factor único del 3,69 % desvía el CIF asignado un **6,8 % en promedio y hasta un 26,3 %** en un procedimiento concreto (drenaje de absceso en Maicao).

No es un matiz académico: en una cotización a la [[Asegurador (EPS)]] esa diferencia se cobra o se pierde.

## Cómo lo resuelve la metodología del proyecto

El pipeline TDABC de la tesis usa **una tasa por minuto de quirófano**:

```
CIF de la cirugía = 420 COP/min × (duracion_real + 25 min) × factor_ineficiencia
```

Los 25 minutos son 15 de preparación prequirúrgica y 10 de cierre y traslado. El factor de ineficiencia institucional (1,28 Maicao · 1,20 Riohacha · 1,15 San Juan) no es parte del CIF: multiplica todo el costo de capacidad del hospital.

El Excel de recolección del profesor ([[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]]) ya venía organizado así: sus hojas 6 a 9 son **cuatro bolsas con inductor propio** — quirófano por minuto, depreciación de equipos, personal indirecto, logística — no un porcentaje.

## El doble conteo: la trampa del modelo

Tres campos del catálogo de parámetros **ya contienen costos indirectos por dentro**:

| Campo | Qué lleva escondido |
|---|---|
| `salas_operatorias.costo_hora` | servicios públicos, mantenimiento, depreciación del inmueble |
| `equipos_medicos.costo_hora` | depreciación y mantenimiento del equipo |
| `recursos_humanos.costos_indirectos_mensuales` | administración imputada a cada persona |

Si además se crea una bolsa CIF de la categoría equivalente, **el mismo costo se cuenta dos veces** y la cirugía sale artificialmente cara. El error es silencioso: nada en la cifra final delata que la luz se pagó dos veces.

La solución del aplicativo: el hospital **marca el origen** de cada componente (`digitado` o `derivado_de_cif`) y el motor excluye del costo directo lo que ya cubre la bolsa. No se ponen los campos a cero — el valor digitado se conserva para comparar "método actual vs. método por bolsas" en la tesis, y para que su nivel de confiabilidad siga siendo verdadero.

## Estado en el aplicativo (completo desde el 2026-09-07)

Ver el informe técnico completo en `docs/cif-implementacion.md` del repositorio.

- ✅ **Capacidad práctica configurable** (`minutos_efectivos_hora`): permite el 80 % de Kaplan & Anderson en vez de asumir 60 min productivos por hora.
- ✅ **Catálogo de bolsas** con categoría, inductor, monto o porcentaje, vigencia y trazabilidad académica.
- ✅ **Mecanismo anti-doble-conteo**: una bolsa y su componente directo no pueden estar encendidos a la vez, el cambio es transaccional y activar exige confirmación nombrando los registros en conflicto.
- ✅ **Motor de asignación**: cada bolsa se reparte por su inductor sobre la capacidad sumada del hospital, el componente directo equivalente se excluye y el `factor_indirecto` se ignora mientras haya bolsas activas.
- ✅ **Auditable**: cada cirugía guarda línea a línea cuánto puso cada bolsa, con su monto mensual, denominador, tasa y unidades. La cuenta se puede rehacer a mano desde la ficha de costo.
- ✅ **Historia intacta**: la cirugía congela los parámetros CIF de su día, así que activar o retocar una bolsa hoy no reescribe lo ya costeado.

**Consecuencia para la tesis:** las bolsas solo aplican a cirugías registradas *después* de activarlas. La comparación «método tradicional vs. método por bolsas» se hace entre periodos, no reprocesando el pasado — que es justamente lo que la hace defendible.

## Preguntas que esto abre para el profesor

- ¿De dónde saldrá el monto mensual de cada bolsa en cada hospital — contabilidad, facturas, presupuesto?
- ¿Se acepta que activar o desactivar una sala cambie las tasas por minuto futuras de todo el hospital?
- ¿El factor de ineficiencia institucional del pipeline (1,15–1,28) debe modelarse en el aplicativo o es solo del análisis de la tesis?

Ver [[Preguntas Abiertas]].
