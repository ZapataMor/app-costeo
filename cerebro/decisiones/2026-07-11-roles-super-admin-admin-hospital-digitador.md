---
tags: [decision, seguridad, ux]
fecha: 2026-07-11
estado: vigente
---

# Tres roles: super_admin, admin_hospital, digitador

## Contexto
La tesis define 6 portales por tipo de usuario (ejecutivo, operativo, individual del cirujano, de costos, comunidades, público). El aplicativo necesitaba un modelo de acceso mínimo para empezar a capturar datos reales.

## Decisión
Enum `RolUsuario` (`app/Enums/RolUsuario.php`) con tres valores, aplicado por el middleware `EnsureRole` (`rol:…`) en `routes/web.php`:

| Rol | Qué puede |
|---|---|
| `super_admin` | Todos los hospitales (switcher), dashboard consolidado, historial |
| `admin_hospital` | Parámetros, cirugías, costeo, pacientes, exportaciones y gestión de digitadores **de su hospital** |
| `digitador` | Solo registrar cirugías y **corregir las que él mismo capturó** (`registrado_por`, gate `corregir-cirugia`); entra directo a `cirugias.index` |

Usuarios pueden desactivarse (`users.activo`, middleware `EnsureUsuarioActivo`).

## Por qué
- El digitador es el usuario de sala/secretaría de quirófano: cuanto menos vea, menos error y menos fricción.
- Separar administración de captura permite que el hospital cargue sus parámetros sin exponer el costeo a quien digita.

## Consecuencias
- El **cirujano no es usuario** aún; el portal individual con benchmarking anónimo es [[backlog-mejoras]] #9.
- Roles y permisos finos (auditor, gerencia) quedan para la capa 5.
