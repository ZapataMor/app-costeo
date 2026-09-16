---
tags: [decision, arquitectura]
fecha: 2026-07-06
estado: vigente
---

# Stack: Laravel 13 + Inertia + React 19 (monolito), MySQL 8

## Contexto
La tesis sugiere "web moderna + API REST + BD relacional + BI open source" (React/Vue/Angular, PostgreSQL/MySQL, Metabase/Superset). El usuario trabaja con Laravel, MySQL y React ([[cliente-y-negocio#7. Alcance confirmado por el usuario (2026-07-06) ✅]]).

## Decisión
- Monolito **Laravel 13** con el starter kit React: **Inertia.js 3 + React 19 + TypeScript + Tailwind 4 + Radix UI**, gráficos con **Recharts**.
- **Sin API pública separada**: las páginas reciben props por Inertia; existe `api/v1/*` (KPIs y captura) pero bajo sesión web, consumida por dashboards y tests.
- **SQLite** en desarrollo/CI, **MySQL 8** en producción (migraciones compatibles).
- Autenticación de **Fortify** (2FA, passkeys) como stub; roles propios ([[2026-07-11-roles-super-admin-admin-hospital-digitador]]).
- Calidad desde el día 1: PHPUnit, PHPStan/Larastan nivel 7, Pint, ESLint, Prettier, GitHub Actions.

## Por qué
- Es el stack del desarrollador → velocidad y mantenibilidad.
- Inertia evita duplicar contratos entre API y front en un equipo de una persona.
- Compatible con lo que la tesis exige; MySQL es de las opciones sugeridas.
- BI open source (Metabase/Superset) queda pospuesto: los dashboards en Recharts cubren la capa 3 sin otro servicio que operar (principio 7 de la tesis: bajo costo de operación).

## Consecuencias
- Si algún día se necesita integración con el HIS de un hospital ([[backlog-mejoras]] #12), habrá que exponer/consumir una API real con autenticación por token.
- Las páginas Inertia dependen del manifiesto de Vite: **`npm run build` antes de `php artisan test`** o el listado responde 500.
