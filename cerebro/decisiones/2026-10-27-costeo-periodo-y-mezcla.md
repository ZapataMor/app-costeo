---
tags: [decision, costeo, indicadores, capa-3, presentacion]
fecha: 2026-10-27
estado: vigente
---

# Costeo quirúrgico: periodo por defecto, comparación a igual mezcla y tabla maestra

## Contexto
Al revisar *Costeo quirúrgico* ([[2026-10-27]]) la primera pantalla no respondía si el servicio gana o pierde: el resultado neto vivía en *Rentabilidad* y la portada mostraba costo promedio, costo más alto, completitud, minutos de sala, glosas y recaudo. Todo se calculaba por defecto sobre **toda la historia**, así que ningún indicador tenía con qué compararse. El único indicador con comparación, «costo promedio por cirugía +23,5 % vs. mes anterior» en rojo, dependía de la **mezcla de procedimientos**: un mes con más colecistectomías sube el promedio aunque nada haya encarecido. Además, cinco de las siete pestañas repetían la misma tabla de procedimientos con otras columnas, el costo estándar (#3) no aparecía en el módulo y la capacidad no utilizada (#2) no estaba en ningún tablero.

## Decisión
1. **Periodo por defecto**: últimos 3 meses naturales con cirugías, anclados a la última cirugía realizada del hospital (no a hoy) y cortados en hoy. «Toda la historia» se pide con `?todo=1`. Vive en el trait `ResuelvePeriodoCosteo` de los controladores web de costeo; la API `/api/v1/kpis/*` conserva «toda la historia» por defecto.
2. **Periodo anterior**: `Periodo::anterior()`, la ventana inmediatamente anterior de la misma duración (meses naturales si el periodo lo es; si no, el mismo número de días).
3. **Variación del costo a igual mezcla**: Σ nᵢ·promedioᵢ(actual) ÷ Σ nᵢ·promedioᵢ(anterior) − 1, con las cantidades del periodo actual y solo los procedimientos presentes en ambos (índice tipo Paasche). Reemplaza la variación del promedio simple como titular.
4. **Desviación frente al estándar agregada**: Σ costo_total ÷ Σ costo_estandar − 1 sobre las cirugías con estándar, no el promedio de `desviacion_pct`.
5. **Capacidad no utilizada en dos cifras que no se mezclan**: salas (minutos sin cirugía × `costo_hora`, salvo si el costo de sala sale de la bolsa de infraestructura) y bolsas CIF de monto mensual prorrateadas por días de vigencia menos `monto_asignado` absorbido. Las bolsas por porcentaje del directo quedan fuera.
6. **Tabla maestra**: una fila por procedimiento con todos sus indicadores (`AnalisisProcedimientos`); Composición pasa a ser una vista de ella y Costos fuera de lo normal se une a Variabilidad. Las rutas viejas redirigen conservando la consulta.

## Por qué
- Un indicador sin referencia no permite decidir; el periodo acotado hace posible la comparación y evita que un mes malo se diluya en años de datos. Anclar a la última cirugía evita la pantalla vacía en hospitales que digitan con retraso.
- La comparación a igual mezcla separa «operamos distinto» de «operamos más caro», que es la pregunta de gestión de la tesis (bajar el costo por procedimiento).
- La desviación agregada pesa cada cirugía por su costo: una cirugía barata desviada no debe pesar lo mismo que una cara.
- La ociosidad de salas digitadas y las bolsas no absorbidas son la misma idea medida en recursos distintos; sumarlas cuando la sala sale de la bolsa contaría dos veces.

## Consecuencias
- Quien comparta un enlace sin fechas verá un periodo que se mueve con la última cirugía; con fechas explícitas el enlace es estable.
- El resultado mensual de la tendencia y el resultado neto solo cuentan cirugías facturadas: el costo de las no facturadas no se resta.
- La capacidad de sala sigue usando la configuración vigente del hospital (decisión previa de `minutosDisponiblesEntre`) y el valor depende de `costo_hora`; la capacidad por recurso humano queda pendiente (#46).
- Los cortes de desviación en la UI (5 % / 20 %) son los del pipeline; los del hospital pueden diferir [VERIFICAR].
