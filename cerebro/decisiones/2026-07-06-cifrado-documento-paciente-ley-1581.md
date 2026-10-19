---
tags: [decision, seguridad, normativa]
fecha: 2026-07-06
estado: vigente
---

# Documento del paciente cifrado + hash para búsqueda (Ley 1581/2012)

## Contexto
Los datos serán **reales** y el Habeas Data se organizará con cada hospital. La tesis exige cumplir la Ley 1581/2012. El sistema necesita identificar pacientes (unicidad, búsqueda) sin exponer el documento.

## Decisión
- `pacientes.documento` se guarda con cast `encrypted`; `documento_hash` (SHA-256, después HMAC según migración `2026_07_13_100000_snapshots_tarifas_estado_en_proceso_y_hmac`) sirve para unicidad por hospital y búsqueda exacta.
- Los KPIs y exportaciones devuelven **solo agregados**; el historial de auditoría identifica al paciente por nombre e id, nunca por documento en claro (`HistorialTest`).

## Por qué
- Cumplimiento verificable de protección de datos sin renunciar a la unicidad.
- Un volcado de la BD no expone documentos.

## Consecuencias
- No se puede buscar por documento parcial (solo coincidencia exacta vía hash).
- Rotar `APP_KEY` exige re-cifrar. La clave HMAC es parte del secreto operativo.
- Pendiente con los hospitales: si el documento debe anonimizarse del todo (pregunta 32 de [[preguntas-levantamiento-hospital]]).
