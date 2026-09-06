---
tags: [fuente, caso-hospitalario, abc, quirofano]
origen: "20538.pdf (117 págs.)"
procesado: 2026-07-11
---

# Fuente — Tesis "Propuesta de Gestión de Costos para las Cirugías del Hospital Municipal del Niño y la Mujer" (U. del Azuay, 2024)

**Autora:** Paola Torres. Directora: Ing. Miriam López. Universidad del Azuay, Cuenca, Ecuador, 2024. Datos reales del periodo 2023.

## Resumen

### Enfoque
Aplica **costeo ABC a cirugías** en un hospital municipal pequeño, y lo complementa con dos herramientas de gestión que las otras fuentes no traen: **punto de equilibrio con mezcla de cirugías** y análisis **justo a tiempo (JIT)**.

### Estructura del análisis
- Cuatro áreas/centros: **recepción, administración, quirófano y hospitalización**. Hallazgo central: el hospital genera más costos en las **actividades secundarias** (administración, recepción, hospitalización) que en la propia cirugía.
- Cuatro cirugías piloto costeadas por ABC (costo de recursos por cirugía): enucleación $1.377,59, cistoscopia $501,73, circuncisión $423,00, septoplastia $1.064,18 (USD). Utilidad conjunta ≈ $4.261/mes.
- Tabla de inductores por recurso y depreciaciones asignadas por porcentaje de uso por área (ej. quirófano absorbe % de depreciación de equipo específico).
- Usa el **registro real de cirugías 2023** del departamento de quirófano (apendicectomías, cesáreas, colecistectomías laparoscópicas… listadas paciente por paciente en anexos) — el mismo tipo de dato crudo que nos darán los hospitales de La Guajira.

### Punto de equilibrio con mezcla (la novedad de esta fuente)
- Clasifica costos en **fijos** (nómina indirecta + depreciaciones ≈ $4.713/mes) y **variables** (honorarios, insumos, servicios).
- Con precios de venta unitarios y márgenes de contribución por cirugía, calcula que el hospital necesita **mínimo 27 cirugías/mes para no perder dinero**, y desagrega cuántas de cada tipo según la mezcla histórica (76% "otras cirugías").
- Esto responde una pregunta gerencial distinta a la del costo unitario: **¿cuánto volumen quirúrgico necesita el hospital para sostenerse?**

## Aportes al cerebro
- Idea directa para los dashboards del aplicativo: además de costo vs. tarifa por procedimiento, un indicador de **punto de equilibrio quirúrgico mensual** (nº mínimo de cirugías según mezcla) — barato de calcular si ya tenemos costos fijos/variables y márgenes.
- Refuerza que los costos "invisibles" están en áreas de apoyo (coincide con Talca: ~40% del costo es apoyo).
- Ejemplo de clasificación fijo/variable aplicable a los parámetros de costos que cada hospital suministrará.

## Citas útiles
> "La institución genera más costos en las actividades secundarias como en el área de administración, recepción y hospitalización."

> "El hospital mensualmente debe realizar mínimo 27 cirugías para alcanzar su punto de equilibrio."
