---
tags: [concepto, nucleo-del-proyecto]
---

# Costeo ABC y TDABC

**La metodología contable central del proyecto.** Es la especialidad del profesor [[Orlando Ruiz]] (escribió un libro sobre ella: [[Fuente - Libro Costeo ABC Hospital San Jose de Maicao]]).

## Costeo Basado en Actividades (ABC)
Filosofía: **los productos/servicios no consumen recursos; consumen actividades, y las actividades consumen recursos.** A diferencia del costeo tradicional (que reparte los costos indirectos con prorrateos arbitrarios), el ABC asigna costos en cadena mediante **inductores** (cost drivers):

```
RECURSOS ──(inductores 1er nivel: m², kWh, nº personas)──► CENTROS DE COSTOS
CENTROS ──(inductores 2º nivel: tiempo, personas/actividad)──► ACTIVIDADES
ACTIVIDADES ──(inductores 3er nivel: nº egresos, nº cirugías)──► OBJETO DE COSTO
```

- **Actividad**: unidad mínima de análisis; "algo que hace la empresa" (ej. valoración médica, administración de medicamentos). En el HSJ se mapearon 15 actividades / 92 tareas para hospitalización.
- **Objeto de costo**: lo que se quiere costear — día de estancia, egreso, procedimiento, paciente, patología o GRD (grupo relacionado de diagnóstico).
- Ventaja clave: **revela costos ocultos** y permite comparar el costo real con la tarifa contratada (¿este servicio da utilidad o pérdida?).

## TDABC (Time-Driven ABC)
Variante simplificada usada en la tesis para cirugías de alto volumen (cesáreas, apendicectomías, colecistectomías...):

```
Costo del procedimiento = Σ (costo por minuto del recurso × minutos de uso) + costo de insumos
```

- **Costo por minuto de un recurso** = costo mensual del recurso (salario+prestaciones, o depreciación+mantenimiento del quirófano) ÷ minutos disponibles al mes.
- Solo requiere dos parámetros por recurso: **tarifa de capacidad** y **tiempo consumido** → mucho más fácil de sistematizar que el ABC completo.
- El instrumento de captura es el Excel de tablas ([[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]]): MOD, medicamentos, insumos, esterilización, quirófano/minuto, depreciación de equipos, personal indirecto, logística.

### Detalles finos del método (del ebook de Kaplan/Anderson — [[Fuente - Ebook TDABC (Kaplan y Anderson)]])
- Los minutos disponibles se calculan con la **capacidad práctica**, no la teórica: ~**80%** de la jornada para personas (descansos, capacitación), ~**85%** para máquinas (mantenimiento). Basta precisión aproximada (±5-10%).
- Las variantes de un proceso se modelan con **ecuaciones de tiempo** (términos aditivos condicionales: `tiempo = base + extra [si complicación] + extra [si conversión a abierta]`), no creando actividades nuevas.
- Como se costea contra capacidad práctica, lo asignado nunca suma el gasto total: la diferencia es el **costo de la capacidad no utilizada** — un KPI gerencial en sí mismo (ej. subutilización de quirófanos).
- El modelo se actualiza **por eventos** (subió el salario → cambia la tasa; mejoró el proceso → cambia el tiempo unitario), sin re-encuestar.

## Uso combinado en la tesis
- **TDABC** → procedimientos frecuentes y estandarizables.
- **ABC** → cirugías complejas (oncológicas, reconstructivas), mapeadas en 7 actividades (preoperatorio → anestesia → preparación → incisión → procedimiento → cierre → recuperación).

## Evidencia que motiva todo
Estudios citados en la tesis (Ecuador, Brasil): los costos reales calculados con ABC/TDABC superan en **más del 50%** lo que los hospitales creían con costeo tradicional. En el HSJ, el ABC mostró que el hospital **perdía dinero por cada día de estancia en habitaciones de 4+ camas** sin saberlo.

## Casos comparables procesados (2026-07-11)
Cuatro casos latinoamericanos de ABC en cirugía/quirófano confirman los patrones del modelo:
- [[Fuente - Caso ABC Hospital Regional de Talca (Chile)]] — piloto de cataratas con 44 casos reales; ~40% del costo es actividades de apoyo; midió 14 h de esperas por paciente.
- [[Fuente - Tesis ABC Quirofano Hospital San Juan de Dios (Cuenca)]] — 5 subprocesos / 17 actividades; MOD >75% del costo; sus 5 cirugías representativas coinciden en parte con nuestros pilotos (cesárea, colecistectomía, apendicectomía).
- [[Fuente - Tesis ABC Quirofano Hospital Leon Becerra (Guayaquil)]] — actividades cronometradas en minutos por centro de costos; recomienda explícitamente construir un software para sostener el ABC.
- [[Fuente - Tesis Gestion Costos Cirugias Hospital del Nino y la Mujer (Cuenca)]] — ABC + **punto de equilibrio con mezcla de cirugías** (nº mínimo de cirugías/mes para no perder).

Relación con otras notas: [[Costos Indirectos (CIF)]] desarrolla la parte más difícil del método —qué hacer con lo que no se rastrea a una cirugía— y su estado en el aplicativo; la [[Cotizacion de Procedimientos]] es la aplicación práctica de este costeo; la comparación costo vs. tarifa determina el margen frente a la [[Asegurador (EPS)]].
