---
tags: [fuente, metodologia, tdabc]
origen: "ebook-costeo-basado-actividades-tiempo.pdf (24 págs.)"
procesado: 2026-07-11
---

# Fuente — Ebook "Costeo Basado en Actividades en función del Tiempo" (TDABC)

Ebook divulgativo de **BiCon Group** (consultora socia de SAS en Latinoamérica) basado directamente en las dos fuentes canónicas del método: *"Time-Driven Activity-Based Costing"* de **Robert S. Kaplan y Steven R. Anderson** (Harvard Business Review, nov. 2004) y *"Prescriptive Financial Transformation"* de Brian Higgins (Wiley, 2018).

> 🔑 **Es el documento más didáctico del cerebro sobre TDABC** — la variante de costeo que usa el modelo del profesor. Explica con números pequeños y verificables la matemática exacta que deberá implementar el motor de costos del aplicativo.

## Resumen

### Por qué el ABC tradicional fracasa en la práctica
- Se construye con **encuestas a empleados** ("¿qué % de tu tiempo dedicas a cada actividad?"): caras, lentas, y la gente reporta porcentajes que suman 100% (nadie declara tiempo ocioso) → las tasas salen infladas asumiendo 100% de utilización.
- Mantenerlo actualizado exige re-encuestar; los modelos grandes explotan en datos (150 actividades × 600.000 objetos de costo × meses = miles de millones de ítems).

### La solución TDABC: solo 2 parámetros por grupo de recursos
1. **Tasa del costo de capacidad** = Costo total de capacidad suministrada ÷ **Capacidad práctica** (en minutos).
2. **Tiempo unitario de cada actividad** (por entrevista u observación directa; precisión aproximada basta, ±5-10%).

**Tasa del conductor de costo = tasa de capacidad × tiempo unitario.**

### Capacidad práctica ≠ capacidad teórica
Regla práctica: la capacidad práctica es **~80% de la teórica para personas** (descansos, capacitación, comunicación) y **~85% para máquinas** (mantenimiento). Ejemplo del ebook: 28 agentes × 8 h/día = 700.000 min/trimestre prácticos; con gastos de $560.000 → **$0,80/minuto**.

### Ecuaciones de tiempo (time equations)
La complejidad real se modela sumando términos condicionales en vez de crear actividades nuevas:
```
Tiempo empaque = 0,5 + 6,5 [si requiere empaque especial] + 2,0 [si envío aéreo]
```
Los datos que activan cada término suelen estar ya en el ERP/sistema transaccional. **Para nuestra app**: esto es la justificación teórica de modelar variantes de una cirugía (complicación, conversión laparoscópica→abierta, reintervención) como términos aditivos de tiempo, no como procedimientos distintos.

### Capacidad no utilizada: el hallazgo gerencial
Al costear con capacidad práctica, la suma de costos asignados **no** iguala el gasto total; la diferencia es el **costo de la capacidad no utilizada** (en el ejemplo de cuentas por pagar: eficiencia 78,43%, exceso equivalente a 2,62 empleados de tiempo completo). Es un KPI en sí mismo: subutilización de quirófanos = plata perdida visible.

### Actualización del modelo
No se re-encuesta: se ajusta la **tasa de capacidad** cuando cambian los precios de los recursos (ej. aumento salarial del 8%) y el **tiempo unitario** cuando mejora la eficiencia (nuevo sistema reduce verificación de 50 a 20 min). Se actualiza por eventos, no por calendario.

## Aportes al cerebro
- [[Costeo ABC y TDABC]]: se añadieron capacidad práctica, ecuaciones de tiempo y capacidad no utilizada.
- [[Glosario]]: términos *capacidad práctica*, *tasa del costo de capacidad*, *ecuación de tiempo*, *capacidad no utilizada*.
- Para el motor de costos del aplicativo: define el algoritmo de cálculo y dos reportes obligatorios (costo por procedimiento y capacidad no utilizada por recurso/sala).

## Citas útiles
> "Para cada grupo de recursos, solamente es necesario estimar dos parámetros: el costo de la capacidad por unidad de tiempo y los tiempos unitarios de las actividades."

> "El objetivo es ser aproximadamente correcto, digamos dentro del 5% al 10% del número real, en lugar de ser preciso."
