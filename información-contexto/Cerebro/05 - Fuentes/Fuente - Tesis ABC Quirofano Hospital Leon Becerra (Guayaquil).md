---
tags: [fuente, caso-hospitalario, abc, quirofano]
origen: "UPS-GT001084.pdf (145 págs.)"
procesado: 2026-07-11
---

# Fuente — Tesis "Sistema de Costos ABC para el Área de Quirófano en el Hospital León Becerra" (UPS Guayaquil, 2015)

**Autora:** Jessenia Toala Bueno. Director: Eco. Miguel Herrera. Universidad Politécnica Salesiana, Guayaquil, Ecuador, abril 2015. Caso: Hospital León Becerra (Benemérita Sociedad Protectora de la Infancia), con estados financieros 2012-2013 y cirugías de agosto-octubre de 2013.

## Resumen

### Situación de partida (espejo de nuestros hospitales)
El quirófano **carecía de todo control de costos directos e indirectos**: sin registro de actividades, sin conocer el valor real de las cirugías, sin sistema de información que separara costos directos de indirectos. El diagnóstico se hizo con entrevistas a directivos y personal + informe de auditoría externa.

### Diseño del modelo ABC
- **Centros de costos alrededor del quirófano**: Emergencia, Admisión y Convenio, Quirófano y Postoperatorio, Proveeduría, Caja. (Lección: el costo de una cirugía no vive solo en la sala — arrastra admisión, caja y suministros.)
- Para cada centro, los funcionarios describieron **cada actividad con su tiempo en minutos y su frecuencia diaria** (ej. "ingreso de paciente ambulatorio: 5 min × 30 veces/día"), y de ahí salen totales de minutos/día, minutos/mes y costo por actividad en dólares — una versión manual de lo que nuestro módulo de registro de tiempos automatizará.
- Costos directos por cirugía = honorarios promedio de médicos + anestesióloga + **"derecho de quirófano"** (tarifa por uso de sala). Los indirectos se distribuyen por generadores (inductores).

### Conclusión más valiosa para nosotros
La primera recomendación de la tesis es literalmente **comprar o construir un software integrado entre áreas para calcular los costos reales** — en 2015 ya era evidente que el ABC hospitalario en hojas sueltas no se sostiene sin sistema.

## Aportes al cerebro
- Confirma el patrón "centros de costos → actividades cronometradas en minutos → costo por actividad" también para hospitales privados/benéficos.
- El concepto de **derecho de sala (costo por uso de quirófano)** aparece aquí como tarifa; en nuestro modelo TDABC equivale al costo/minuto de sala × duración.
- Antecedente citable de que la solución al problema es un **sistema de información**, no más contabilidad manual (justifica el aplicativo ante los directivos).

## Citas útiles
> "El área de quirófano del Hospital León Becerra de Guayaquil carece de un control integral de costos directos e indirectos de las actividades."

> "Para el éxito de este sistema se recomienda la compra o la realización de un sistema integrado entre áreas, es decir, un software que facilite calcular los costos reales."
