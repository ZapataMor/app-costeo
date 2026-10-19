---
tags: [decision, arquitectura, seguridad]
fecha: 2026-07-06
estado: vigente
---

# Multi-tenant con base de datos única y `hospital_id`

## Contexto
El alcance confirmado es **multi-hospital genérico**: cualquier IPS se registra con sus propios parámetros. La plataforma tecnológica de la tesis es **compartida entre los 3 hospitales** (economías de escala, red departamental).

## Decisión
- Una sola base de datos; **cada tabla de dominio referencia `hospitales.id`**.
- Trait `BelongsToHospital` + scope global `HospitalScope` (`app/Models/Concerns`, `app/Models/Scopes`): filtra toda consulta por el hospital activo y lo asigna automáticamente al crear.
- El hospital activo se resuelve en `app/Support/HospitalContext.php`: por defecto el del usuario (`users.hospital_id`); el `super_admin` lo cambia con un switcher (`HospitalActivoController`, middleware `SetHospitalContext`).
- Route-model-binding hereda el scope → un recurso de otro hospital responde **404** sin código adicional. Las reglas `exists` de los Form Requests se acotan al hospital.

## Por qué
- Operación y despliegue más simples que BD por tenant (un solo esquema, una migración).
- El aislamiento por scope global es verificable con tests (`TenantIsolationTest`).
- Permite a futuro el módulo de comparación entre hospitales ([[backlog-mejoras]] #17) sin mover datos.

## Consecuencias
- Toda consulta cruda (SQL en `PersonalCosteoService`, exportaciones) **debe** incluir el filtro por hospital explícitamente: el scope solo protege Eloquent.
- Cualquier tabla nueva de dominio debe usar el trait; el agente [[qa-seguridad]] lo revisa.
