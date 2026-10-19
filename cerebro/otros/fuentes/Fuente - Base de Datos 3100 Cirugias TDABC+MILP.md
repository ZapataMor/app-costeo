---
tags: [fuente, instrumento, datos, tdabc, milp]
origen: "originales/profesor/Base_Datos_3000_Cirugias_TDABC_MILP.xlsx (8 hojas; recibido como 'Base_Datos_3000_Cirugias_TDABC_MILP (1).xlsx')"
procesado: 2026-09-15
---

# Fuente — Base de datos de 3.100 cirugías (pipeline TDABC + MILP)

Libro Excel que acompaña a la [[Fuente - Guia de Campos Base de Datos TDABC+MILP]]: el **resultado del pipeline** del tercer objetivo de la tesis de [[cliente-y-negocio|Orlando Ruiz]]. Contiene 3.100 cirugías **simuladas** (2022-01 → 2024-12) de los tres hospitales, costeadas por TDABC y reprogramadas por MILP.

> ⚠️ **Datos simulados por el pipeline, no observaciones de campo.** Sirven para validar fórmulas, dimensionar magnitudes y probar el aplicativo con volumen realista — no como evidencia empírica de costos de los hospitales. Ya se usó la hoja `Analisis_TDABC` para medir el error del factor plano de CIF ([[Costos Indirectos (CIF)]]).

## Estructura del libro

| Hoja | Filas | Contenido |
|---|---|---|
| `Base_Datos_Completa` | 3.100 × 25 | Una fila por cirugía: `id_cirugia`, hospital, municipio, fecha, trimestre, tipo, especialidad, urgencia, `duracion_plan_min`, `duracion_real_min`, edad, sexo, `costo_tdabc_real_cop`, `costo_tdabc_estandar_cop`, `costo_optimizado_cop`, ahorro absoluto y relativo, ROI, `eficiencia_tiempo_pct`, `pct_capacidad_ociosa`, `pct_overtime`, `desviacion_costo_pct`, `categoria_desviacion`, `nivel_ahorro_milp`, `milp_status` |
| `Resumen_Hospital` | 3 + total | N, duración promedio, costo real y optimizado, ahorro, ROI, eficiencia, capacidad ociosa, overtime, desviación |
| `Resumen_Tipo_Cirugia` | 15 tipos | Lo mismo por tipo de cirugía, ordenado por ahorro |
| `Analisis_TDABC` | 45 (3 hosp. × 15 tipos) | **Componentes de costo promedio**: estándar, real, personal, equipo, materiales, CIF, overtime, capacidad ociosa, desviación, `tasa_minuto_qx_cop` = 8.440 |
| `Analisis_MILP` | 36 (3 × 12 trimestres) | Costo real vs. optimizado, ahorro, overtime real vs. optimizado (siempre 0 tras MILP), instancias óptimas |
| `KPIs_Mensuales` | 108 (3 × 36 meses) | Serie mensual en millones COP: costo real/optimizado, ahorro, eficiencia, capacidad ociosa, overtime, desviación |
| `Graficos` | — | Tablero de gráficos embebidos (no datos) |
| `Metodologia` | 27 líneas | Tasas, factor de ineficiencia, formulación MILP, fórmulas de KPIs, referencias |

## Cifras clave

**Por hospital (2022-2024)**

| Hospital | Cirugías | Dur. prom. | Costo real total | Ahorro MILP | Desviación prom. |
|---|---|---|---|---|---|
| HNR Riohacha | 1.302 | 58,0 min | $1.659,5 M | 13,1 % | 20,6 % |
| HSJ Maicao | 1.023 | 57,9 min | $1.391,2 M | 16,3 % | 28,5 % |
| HSR San Juan | 775 | 56,8 min | $926,9 M | 11,7 % | 16,2 % |
| **Total** | **3.100** | 57,7 min | **$3.977,7 M** | **13,8 % ($589 M)** | 22,1 % |

La desviación promedio (real vs. estándar) coincide con el factor de ineficiencia de cada hospital (1,28 · 1,20 · 1,15): es el mismo supuesto visto desde otro ángulo.

**Los 15 tipos de cirugía simulados** (N, duración planificada, costo real promedio): Cesárea (447, 48 min, $1,10 M) · Colecistectomía laparoscópica (398, 75, $1,68 M) · Apendicectomía laparoscópica (332, 62, $1,47 M) · Hernioplastia inguinal (274, 55, $1,16 M) · Legrado uterino (238, 28, $0,73 M) · Herniorrafia umbilical (210, 45, $1,01 M) · Histerectomía abdominal (194, 95, $1,82 M) · Osteosíntesis de fractura (179, 90, $2,08 M) · Hemorroidectomía (150, 35, $0,80 M) · Reducción de fractura cerrada (145, 42, $0,90 M) · Laparotomía exploratoria (134, 85, $1,64 M) · Traqueostomía (114, 40, $0,95 M) · Drenaje de absceso (106, 25, $0,67 M) · Colostomía (92, 70, $1,45 M) · Amputación de extremidad (87, 65, $1,36 M). Especialidades: Cirugía General, Ginecología-Obstetricia, Ortopedia y Traumatología, Otorrinolaringología.

**Componentes de una cesárea en HSJ** (hoja `Analisis_TDABC`): estándar $901.801 · real $1.152.302 · personal $384.659 · equipo $439.532 · materiales $220.682 · CIF $38.864 · overtime $5.293 · capacidad ociosa $767. Nótese que la cesárea del pipeline (~$1,1 M) **duplica** la cesárea de $520.000 del capítulo 5: son dos parametrizaciones distintas del mismo profesor — las tasas del pipeline (cirujano 1.550 COP/min ≈ $93.000/h) son casi el doble de las del ejemplo del capítulo ($50.000/h).

**Fórmulas de la hoja `Metodologia`**
```
costo_optimizado_cop   = costo_tdabc_real − ahorro_milp_proporcional
ahorro_relativo_pct    = ahorro_milp_semana / costo_real_semana × 100
eficiencia_tiempo_pct  = duracion_plan / duracion_real × 100
pct_capacidad_ociosa   = costo_capacidad_ociosa / costo_real × 100
pct_overtime           = costo_overtime / costo_real × 100
MILP: Min ΣΣ c_ij·x_ij + α·Σ y_ij  s.a. cobertura, capacidad, secuencia — CBC/PuLP, gap 5 %, 60 s, nivel semana × hospital
```

## Aportes al cerebro
- **Dataset de prueba realista** para el aplicativo: 15 procedimientos con duraciones planificadas, mezcla por hospital y estacionalidad mensual → candidato a seeder/fixture de validación del motor ([[backlog-mejoras]] #19).
- Confirma la lista de campos que el aplicativo debería producir para ser comparable con la tesis: `costo_tdabc_estandar`, `costo_tdabc_real`, `costo_capacidad_ociosa`, `desviacion_costo_pct`, `categoria_desviacion`, `pct_overtime` ([[backlog-mejoras]] #1-#4).
- Evidencia la **doble parametrización** (capítulo 5 vs. pipeline) que hay que aclarar con el profesor ([[preguntas-abiertas]]).
- Referencias bibliográficas del pipeline: Kaplan & Anderson (2004); Brailsford et al. (2019) *OR in healthcare*, EJOR; Zhu et al. (2019) *OR planning and scheduling*; Caballini et al. (2021) *MILP for surgical scheduling*, IJPR.
