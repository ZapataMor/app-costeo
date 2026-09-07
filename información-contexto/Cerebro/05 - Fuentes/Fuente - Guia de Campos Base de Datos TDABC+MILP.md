---
tags: [fuente, instrumento, metodologia]
origen: "Guia_Campos_Base_Datos_TDABC_MILP.docx + Base_Datos_3000_Cirugias_TDABC_MILP.xlsx"
procesado: 2026-09-06
---

# Fuente — Guía de Campos de la Base de Datos TDABC + MILP

Documento metodológico de **agosto 2026** (v1.0) que describe los **54 campos** de la base de datos integrada de **3.100 cirugías simuladas** construida con el pipeline computacional **TDABC + MILP** del tercer objetivo de la tesis de [[Orlando Ruiz]].

Es la fuente más precisa que tenemos sobre **cómo debe calcular el aplicativo**: da fórmulas exactas, tasas en pesos y rangos de valores.

## Los tres hospitales y su factor de ineficiencia

| Hospital | Código | Score CMM | Factor ineficiencia | Cirugías |
|---|---|---|---|---|
| E.S.E. Hospital San José de Maicao | HSJ | 2,755 | 1,28 (+28 %) | 1.023 (33 %) |
| Hospital Nuestra Señora de los Remedios (Riohacha) | HNR | 3,149 | 1,20 (+20 %) | 1.302 (42 %) |
| Hospital San Rafael II (San Juan del Cesar) | HSR | 3,429 | 1,15 (+15 %) | 775 (25 %) |

El factor multiplica **todo el costo de capacidad** (personal, equipo, CIF, no los materiales) y representa la ineficiencia institucional medida con el modelo de madurez. Período simulado: 2022-01 a 2024-12.

## Tasas de costo TDABC (COP/minuto)

| Recurso | COP/min | COP/hora |
|---|---|---|
| Cirujano | 1.550 | 93.000 |
| Anestesiólogo | 1.350 | 81.000 |
| Instrumentadora | 600 | 36.000 |
| Enfermera quirúrgica | 720 | 43.200 |
| Auxiliar de enfermería | 480 | 28.800 |
| **Uso de quirófano** | **3.800** | 228.000 |
| Equipo de anestesia | 950 | 57.000 |
| Equipo de laparoscopia | 1.200 | 72.000 |
| **CIF base** | **420** | 25.200 |

## Tiempos de presencia por recurso

```
t_total = duracion_real + 25      (15 min pre + 10 min post)
cirujano        = duracion_real + 12,5
instrumentadora = duracion_real + 15
anestesiólogo, enfermera, quirófano, equipos, CIF = t_total
auxiliar        = t_total × 0,60
laparoscopia    = t_total si aplica, 0 si no
```

Esto es una **ecuación de tiempo** en el sentido de Kaplan/Anderson: cada recurso tiene su propia ventana de presencia, no todos ocupan el mismo tiempo que dura la cirugía. Ver [[Costeo ABC y TDABC]].

## Lo importante para el aplicativo: el CIF va por minuto

```
tdabc_cif_cop = 420 COP/min × t_total × factor_ineficiencia
```

**El CIF no es un porcentaje del costo directo: es una tasa por minuto de quirófano.** Confirma que la base de asignación `minuto_quirofano` que el aplicativo ya modela es la correcta, y que el `factor_indirecto` plano que usa hoy el motor es una aproximación. Ver [[Costos Indirectos (CIF)]].

Los materiales son el único componente que **no** se multiplica por el factor de ineficiencia: se consumen por cantidad física, no por tiempo de capacidad.

## Capacidad práctica: 80 %

La restricción R2 del MILP usa `T_disp = 80 % de 480 min` por quirófano y día. Coincide con la regla de Kaplan/Anderson (80 % para personas, 85 % para máquinas) y con el parámetro `minutos_efectivos_hora` que el aplicativo ya tiene: 48 min efectivos por hora es ese mismo 80 %.

## Estructura de los 54 campos

| Grupo | Campos | Contenido | Módulo |
|---|---|---|---|
| A | 1–11 | Identificación y clasificación | `generador.py` |
| B | 12–17 | Operativos y clínicos | `generador.py` |
| C | 18–27 | **TDABC: componentes de costo** | `tdabc.py` |
| D | 28–31 | MILP: optimización semanal | `milp.py` |
| E | 32–40 | KPIs integrados TDABC + MILP | `pipeline.py` |
| F | 41–54 | Operativos adicionales y contextuales | `generador.py` + `pipeline.py` |

### Campos de costo que el aplicativo debería poder producir

- `costo_tdabc_estandar_cop` — costo con la duración **planificada** y **sin** factor de ineficiencia: el costo ideal, referencia del desperdicio.
- `costo_tdabc_real_cop` — con duración real, sobretiempos y factor de ineficiencia.
- `costo_capacidad_ociosa_cop` — `max(0, estándar − real)`: el **costo de la capacidad no utilizada**, que en TDABC es un KPI gerencial en sí mismo.
- `desviacion_costo_pct` y `categoria_desviacion` — clasificación ordinal: `Subejecucion` (≤ −5 %), `En_Rango` (−5 a 5 %), `Sobrecosto_Leve` (5 a 20 %), `Sobrecosto_Alto` (> 20 %). **Es casi exactamente el detector de sobrecostos que el aplicativo ya tiene**; conviene alinear los umbrales.
- `tdabc_overtime_cop` — sobrecosto del 50 % por cirugía que pasa de las 15:00 (minuto 900). El aplicativo **no modela horas extras** todavía.

## El MILP (tercer objetivo, fuera del alcance del aplicativo por ahora)

Programación entera mixta que reasigna cirugías a quirófanos y días para minimizar apertura de salas + sobretiempo + cancelaciones, con balance de carga ±30 % y prohibición de cancelar urgencias. Solver PuLP/CBC, gap 5 %.

Resultados simulados: ahorro del **11,7 % al 16,2 %** según el hospital, sobre un costo real total de ~3.978 millones COP en el trienio.

## Contenido del Excel adjunto

`Base_Datos_3000_Cirugias_TDABC_MILP.xlsx`, 8 hojas: `Base_Datos_Completa` (3.100 filas × 25 columnas), `Resumen_Hospital`, `Resumen_Tipo_Cirugia`, **`Analisis_TDABC`** (componentes de costo promedio por hospital × tipo — la hoja usada para medir el error del factor plano en [[Costos Indirectos (CIF)]]), `Analisis_MILP`, `KPIs_Mensuales`, `Graficos`, `Metodologia`.

> ⚠️ Los datos son **simulados** por el pipeline, no observaciones de campo. Sirven para validar fórmulas y dimensionar magnitudes, no como evidencia empírica de los costos reales de los tres hospitales.
