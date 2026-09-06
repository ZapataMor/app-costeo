---
tags: [actor]
---

# Paciente

La persona que recibe la atención. Alrededor de él gira la [[Cuenta del Paciente]].

## Rol en el dominio
- Su ingreso **abre la cuenta**; su egreso la cierra.
- Su condición clínica determina la [[Cotizacion de Procedimientos]] inicial y el riesgo de [[Complicaciones y Escalamiento de Costos]].
- Su **tipo de afiliación** determina quién paga: EPS, SOAT, ARL, gobierno, o él mismo.

## El "responsable"
Si el paciente no tiene aseguramiento (o hay copagos), al ingreso firma un **responsable** (familiar/acudiente) que asume la deuda. Es un actor secundario pero real en la facturación.

## Rol probable en el aplicativo
Entidad de datos (no usuario del sistema): datos demográficos, afiliación, diagnósticos, procedimientos, y su cuenta asociada.
