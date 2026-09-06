---
tags: [concepto]
---

# Cuenta del Paciente

La **"hoja de vida" financiera** que se abre cuando un [[Paciente]] ingresa a una [[Hospital o Clinica (IPS)]]. Es el objeto central del aplicativo.

## Qué es
- Se abre en el momento del ingreso (admisión) y se cierra al egreso (alta, remisión o fallecimiento).
- Acumula **todos los gastos** de la atención: consultas, exámenes de laboratorio, imágenes, medicamentos, insumos, honorarios médicos, estancia (habitación, UCI), cirugías, traslados.
- En la terminología hospitalaria colombiana se conoce como **cuenta de paciente** o **factura de servicios de salud**; el proceso que la administra se llama **facturación de cuentas médicas**.

## Ciclo de vida
1. **Admisión** → se crea la cuenta, se identifica el pagador ([[Asegurador (EPS)]], [[Gobierno (ADRES)]], o particular).
2. **Atención** → cada servicio prestado se registra como un cargo (idealmente con código CUPS, ver [[Glosario]]).
3. **Cotización inicial** → si hay un procedimiento programado, se estima el costo esperado ([[Cotizacion de Procedimientos]]).
4. **Eventos** → complicaciones agregan cargos no previstos ([[Complicaciones y Escalamiento de Costos]]).
5. **Egreso y cierre** → se consolida la factura y se radica ante el pagador.
6. **Auditoría / glosas** → el pagador puede objetar cargos (glosa) y el hospital debe responder.

## Quién paga
- **Con aseguramiento**: la EPS (régimen contributivo o subsidiado) o una aseguradora (SOAT, medicina prepagada, ARL).
- **Sin aseguramiento**: el paciente o su **responsable** (familiar que firma al ingreso). En urgencias vitales el Estado puede cubrir (población pobre no asegurada).

## Relación con el aplicativo
La cuenta es la entidad que el sistema debe modelar: costo **esperado** (cotización) vs. costo **real** (cargos acumulados) vs. costo **proyectado** (si ocurren complicaciones).

## Fuentes
*Pendiente — se llenará con los documentos del profesor.*
