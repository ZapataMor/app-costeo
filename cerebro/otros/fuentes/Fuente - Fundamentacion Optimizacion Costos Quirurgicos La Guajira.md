---
tags: [fuente, tesis-doctoral, metodologia, tdabc, milp]
origen: "originales/profesor/Fundamentacion_Optimizacion_Costos_Quirurgicos_LaGuajira.docx (informe académico, ~136.000 caracteres)"
procesado: 2026-09-15
---

# Fuente — Fundamentación teórica y metodológica para la optimización de costos quirúrgicos en La Guajira

Informe académico doctoral que **justifica la inclusión de un nuevo objetivo en la tesis** de [[cliente-y-negocio|Orlando Ruiz]]: *"Optimizar los costos de los servicios de cirugía de los tres hospitales de mediana complejidad de La Guajira mediante la construcción de una base de datos de los costos asociados a más de 3.000 cirugías."* Es el antecedente conceptual de la [[Fuente - Guia de Campos Base de Datos TDABC+MILP]] y de la [[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]].

> 🔑 Explica **por qué** el pipeline TDABC+MILP existe y qué espera medir la tesis con él. Para el aplicativo aporta dos cosas: un **segundo modelo de datos** (9 entidades, más clínico que el del capítulo 5) y una **lista de 20 indicadores con fórmula, línea base y meta** que los dashboards deberían poder alimentar.

## Resumen

### Tesis central
Los quirófanos representan el 40-60 % del costo operativo hospitalario. Los hospitales públicos de La Guajira (HSJ, HNR, HSR) no tienen sistemas de costeo que permitan documentar la brecha entre costo real y tarifa reconocida. El ANOVA previo de la tesis (F = 852.303, p < 0,001, η² = 0,792) muestra diferencias significativas entre los tres hospitales → hay prácticas transferibles y se justifican estrategias diferenciadas.

### Modelo elegido: híbrido TDABC + MILP con ML predictivo (sección 4.5)
1. **TDABC** para costear cada procedimiento e identificar inductores (tiempo de quirófano, insumos, personal). Evidencia citada: reducciones de costos del 15-30 % en hospitales de ingresos bajos y medios.
2. **ML (Random Forest, XGBoost)** para predecir duraciones quirúrgicas de procedimientos de alto volumen.
3. **MILP** multi-objetivo para asignar y secuenciar cirugías: minimizar horas regulares + extras, minimizar esperas por prioridad, maximizar utilización de quirófanos y especialistas, reservar capacidad para urgencias. Evidencia: +10-25 % de capacidad sin recursos adicionales.
4. **Validación retrospectiva** contra las 3.000+ cirugías históricas.

Ventajas argumentadas: viabilidad con software libre (Python/R, GLPK/CBC), calibrable por hospital, actualizable con nuevos datos, aceptabilidad si participan cirujanos, anestesiólogos y enfermería.

### Metodología en 7 fases (sección 5)
Diseño de la BD → recolección (12-24 meses de cirugías, todas las especialidades, electivas/urgentes/emergentes; **observación directa de un 10-15 % de los casos** para calibrar tiempos; entrevistas para tasas de capacidad) → depuración y validación (rangos plausibles, outliers, imputación) → TDABC → MILP → validación → piloto. Cronograma de recolección: 6-8 meses. Consideraciones éticas: comités de ética, anonimización, consentimiento para observación.

### Modelo de datos propuesto (sección 5.1) — comparar con el del aplicativo
| Entidad | Campos que el aplicativo **no** tiene hoy |
|---|---|
| Pacientes | edad, sexo, **peso, talla, IMC, municipio, comorbilidades, clasificación ASA** |
| Procedimientos | prioridad **electiva / urgente / emergente** (hoy solo programada/urgencia), diagnóstico pre y **post**-operatorio, resultado (alta / traslado / defunción) |
| Tiempos quirúrgicos | tiempo de **anestesia**, tiempo de **recuperación post-anestésica**, **hora de inicio programada vs. real** |
| Insumos | tipo **implante** como categoría propia |
| Servicios de apoyo | **laboratorio, imagenología, banco de sangre, patología** por cirugía — no existe la entidad |
| Costos indirectos | método de asignación por minuto de quirófano o por procedimiento (✅ ya cubierto por las bolsas CIF) |
| Hospitales | nº de quirófanos, camas de hospitalización y de recuperación, personal por especialidad |

### 20 indicadores de resultado esperados (sección 8) — con fórmula, línea base y meta
**Eficiencia operativa**: (1) utilización de quirófanos 60-70 % → 75-85 %; (2) nº de cirugías/año +10-25 %; (3) **turnover time** entre cirugías 40-60 min → 25-35; (4) tasa de cancelación 10-20 % → 5-10 %; (5) tasa de horas extra 15-25 % → 5-10 %.
**Eficiencia económica**: (6) costo promedio por procedimiento ($2-3 M COP) −15-30 %; (7) costo de horas extra ($50-100 M/año/hospital) −50-70 %; (8) **brecha costo real vs. tarifa reconocida** (10-30 %); (9) costo de insumos por procedimiento −10-20 %; (10) ahorro total anual $900-1.800 M COP para los tres hospitales.
**Calidad asistencial** (no deben empeorar): (11) complicaciones intraoperatorias 2-5 %; (12) postoperatorias a 30 días 5-10 %; (13) mortalidad a 30 días 0,5-2 %; (14) espera para cirugía electiva 30-90 días → −20-40 %; (15) satisfacción 70-80 %.
**Gestión del conocimiento**: (16) **completitud de la BD > 90 %**; (17) latencia cirugía → registro < 7 días; (18) semanas con uso del modelo > 80 %; (19) 3-5 personas capacitadas por hospital; (20) 5-10 mejoras/año basadas en datos.

## Aportes al cerebro
- Explica el origen del pipeline y de las tasas usadas en [[Costos Indirectos (CIF)]] y en el [[backlog-mejoras]] (#1-#5).
- Nuevas mejoras candidatas: indicadores operativos (turnover, cancelaciones, horas extra, espera electiva, latencia de registro) y campos clínicos (ASA, comorbilidades, prioridad de tres niveles, servicios de apoyo) — ver [[backlog-mejoras]] #18-#20.
- Confirma que el MILP y el ML son objetivos de la tesis, **no del aplicativo** por ahora; pero el aplicativo es la fuente natural de la base de datos real que los alimentará.
- Términos nuevos en el [[Glosario]]: MILP, clasificación ASA, turnover time, prioridad quirúrgica.

## Citas útiles
> "El nuevo objetivo propuesto es: 'Optimizar los costos de los servicios de cirugía de los tres hospitales de mediana complejidad del departamento de La Guajira, mediante la construcción de una base de datos de los costos asociados a la realización de más de 3.000 cirugías en estos hospitales.'"

> "Los hospitales públicos enfrentan déficits operativos crónicos debido a brechas entre costos reales y tarifas reconocidas, que no pueden documentarse adecuadamente sin sistemas de costeo precisos."

> "La granularidad de datos propuesta balancea la necesidad de información detallada con la viabilidad de recolección en hospitales con sistemas de información limitados."

⚠️ Las secciones 5.4 (Implementación de TDABC) y 5.5 (Modelo de optimización) del Word aparecen solo como títulos sin cuerpo en la versión recibida — [VERIFICAR] si existe una versión completa.
