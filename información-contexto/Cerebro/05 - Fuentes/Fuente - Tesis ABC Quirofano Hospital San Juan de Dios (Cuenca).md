---
tags: [fuente, caso-hospitalario, abc, quirofano]
origen: "Trabajo-de-Titulación.pdf (126 págs.)"
procesado: 2026-07-11
---

# Fuente — Tesis "Aplicación del Sistema de Costos ABC: Área de Quirófano del Hospital San Juan de Dios" (U. de Cuenca, 2024)

**Autoras:** Denisse Méndez y Camila Pallango. Director: Marco Peralta. Universidad de Cuenca, Ecuador, marzo 2024. Caso: ASOGALENICA S.A. — Hospital San Juan de Dios, con datos reales del año 2022.

> 🔑 De las tres tesis ecuatorianas, **esta es la más cercana a nuestro dominio**: ABC aplicado al quirófano usando las cirugías más representativas del hospital — la misma lógica de los "10 procedimientos principales" que pediremos en Maicao. Además es de 2024 (estado del arte reciente).

## Resumen

### Estructura del proceso quirúrgico (reutilizable como modelo)
Levantaron el proceso completo porque **no existía protocolo formal** (situación esperable también en La Guajira): **5 subprocesos → 17 actividades**, agrupados en: ingreso del paciente → valoración prequirúrgica → preparación del quirófano → intervención → recuperación. La actividad más costosa: la nº 13, **"realización de la cirugía programada"**.

### Las 5 cirugías representativas (según reportes de contabilidad, 1.358 cirugías en 2022)
| Cirugía | Nº casos | % | Costo unitario (USD) |
|---|---|---|---|
| Parto único por cesárea | 221 | 16,3% | $1.275,17 |
| Colelitiasis | 137 | 10,1% | $1.089,70 |
| Leiomioma del útero | 99 | 7,3% | $1.584,73 |
| Apendicitis aguda | 67 | 4,9% | $1.159,68 |
| Desviación del tabique nasal | 37 | 2,7% | $2.263,12 |

Nótese que **cesárea, colelitiasis (colecistectomía) y apendicitis coinciden con los procedimientos piloto de la tesis del profesor**.

### Hallazgos de estructura de costos
- **Mano de obra directa > 75% del costo total** (los honorarios médicos se trataron como costo directo con tarifa estandarizada; enfermeras y auxiliares como indirecto).
- **Costos indirectos ≈ 25%**, que el hospital asumía "sin saberlo" por carecer de sistema de costeo.
- No hay "materia prima" identificable como tal en servicios quirúrgicos: todo entra como MOD + CIF.
- Inductores por recurso: nº de cirugías realizadas (asignación final), horas de enfermería en quirófano, consumo de insumos/medicamentos por porcentaje, minutos por actividad para remuneraciones.

## Aportes al cerebro
- Confirma el patrón de captura que necesita el aplicativo: actividades con **tiempo en minutos por rol** + consumo de insumos por cirugía.
- Sus 17 actividades del quirófano son un checklist para validar el catálogo de actividades que definamos con los hospitales de La Guajira.
- Advertencia útil: cuando el inductor de distribución es "nº de cirugías", los procedimientos poco frecuentes salen artificialmente caros (el tabique nasal fue el más costoso *porque* fue el menos realizado). El motor de costos debe dejar claro ese efecto en los reportes.
- Refuerza [[Costeo ABC y TDABC]] y la [[Cotizacion de Procedimientos]].

## Citas útiles
> "El elemento del costo que más peso representa dentro del total de costo del servicio es la mano de obra directa con más del setenta y cinco por ciento del total."

> "Los costos indirectos de fabricación […] son asumidos por el hospital sin el conocimiento de que su representatividad indica alrededor del veinticinco por ciento del total del costo del servicio ofrecido, a causa de la carencia de un proceso o sistema de información."
