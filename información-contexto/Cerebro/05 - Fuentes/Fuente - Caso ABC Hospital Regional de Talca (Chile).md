---
tags: [fuente, caso-hospitalario, abc]
origen: "cbc,+XVICongresso_artigo_0560.pdf (15 págs.)"
procesado: 2026-07-11
---

# Fuente — Caso "Costos ABC en la gestión hospitalaria chilena: Hospital Regional de Talca"

**Autores:** José Antonio Tello, Patricia Rodríguez y Constanza Soto (Universidad de Talca). Ponencia en el XVI Congresso Brasileiro de Custos, Fortaleza, 2009.

> 🔑 **Es el precedente metodológico más parecido a lo que haremos en La Guajira**: elegir una prestación piloto representativa, seguir casos reales de principio a fin, y con eso costear y mejorar el proceso. Exactamente el espíritu de los "10 procedimientos principales" del HSJ Maicao.

## Resumen

### Contexto
Reformas chilenas (Plan AUGE, hospitales autogestionados desde 2009) obligaron a los hospitales públicos a gestionarse con costos reales — el mismo tipo de presión normativa que la Ley 100/1993 y la Ley 1438/2011 ponen sobre las E.S.E. colombianas.

### Diseño del estudio
- **Prestación piloto:** cirugía de **cataratas** (representativa en complejidad y volumen).
- **Seguimiento de 44 casos reales completos**, desde la consulta por sospecha hasta el alta (11 prestaciones por paciente: consulta, ecografía/ecobiometría, electrocardiograma, exámenes, pase operatorio, consulta previa, reunión con enfermera, cirugía y 3 controles postoperatorios).
- Metodología Cooper & Kaplan en **12 etapas**: diagnóstico → definición del problema → marco teórico → identificar prestaciones → identificar procesos/actividades/recursos → seguimientos repetitivos → rediseño de procesos → jerarquizar actividades → elegir inductores → costear actividades → asignar a procesos → informe.

### Inductores usados (tabla directamente reutilizable)
| Recurso | Inductor |
|---|---|
| Remuneraciones | Horas/hombre |
| Aseo, electricidad, agua, calefacción, depreciación | m² × tiempo de utilización |
| Esterilización | m³ de material esterilizado |
| Lavandería | kilos de ropa |
| Archivo | nº de fichas |
| Insumos, medicamentos | cantidad utilizada |

### Resultados
- **Costo de la atención integral de cataratas: $185.925 CLP por paciente** (la cirugía en sí: $144.737; el resto son prestaciones complementarias).
- Costos directos (insumos, medicamentos, remuneraciones) = **~60%** del total; el otro **40% son actividades de apoyo** — invisible con costeo tradicional.
- **Tiempos muertos**: cada paciente pierde **13,95 horas esperando** a lo largo del proceso; extrapolado al año, equivale a 134 atenciones que se dejan de hacer.
- Identificaron **actividades sin valor añadido** (exámenes innecesarios, evaluaciones duplicadas) candidatas a eliminarse.

## Aportes al cerebro
- Valida el enfoque de la tesis del profesor con un caso público latinoamericano publicado: piloto por procedimiento + seguimiento de casos reales + ABC.
- La tabla de inductores y el desglose por prestación sirven de referencia para el modelo de datos del aplicativo (una cirugía = cadena de prestaciones, no un evento único).
- Idea exportable a los dashboards: **medir tiempos de espera del paciente** como subproducto de la captura de tiempos (conecta con el portal operativo de salas del [[Fuente - Capitulo 5 Tesis Doctoral SGC|capítulo 5]]).

## Citas útiles
> "Los resultados de la investigación demuestran que el modelo de costos basados en actividades es efectivo para medir y controlar los costos de la prestación, más aún, es una herramienta eficaz para mejorar los procesos."

> "En total, cada paciente pierde casi 14 horas en el proceso […] se dejan de efectuar 134 atenciones al año de esta patología."
