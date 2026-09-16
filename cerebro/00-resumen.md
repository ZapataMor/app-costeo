---
tags: [moc, resumen]
actualizado: 2026-09-15
---

# 🧠 app-costeo (SICOPH) — Resumen

> **Punto de entrada del cerebro.** Léelo antes de cualquier tarea. Si cambia el estado del proyecto, actualiza esta nota.

## Qué es

**SICOPH** — Sistema de costeo de procedimientos hospitalarios por **TDABC** (Time-Driven Activity-Based Costing). Aplicativo web multi-hospital que captura tiempos quirúrgicos, consumos y parámetros de costo por hospital, calcula el **costo real de cada cirugía**, lo compara contra la tarifa facturada (referencia SOAT −25 %) y expone KPIs y dashboards.

Es la materialización tecnológica de la tesis doctoral del profesor **Orlando Ruiz** (*Sistema de Gestión del Conocimiento para la Optimización de los Costos del Servicio de Cirugía de los Hospitales de Mediana Complejidad de La Guajira*). Contexto completo en [[cliente-y-negocio]].

## Cliente

- **Orlando Ruiz** — profesor de Uniguajira, doctorando, ex gerente del Hospital San José de Maicao. Dueño de la visión y del modelo de costeo.
- **Desarrollador**: Luis Felipe Zapata (contratado; ver términos comerciales en [[cliente-y-negocio#Acuerdo comercial]]).
- **Beneficiarios**: los 3 hospitales públicos de mediana complejidad de La Guajira (HSJ Maicao, HNR Riohacha, HSR San Juan del Cesar) como punto de partida; el sistema es genérico para cualquier IPS.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3 · **Laravel 13** · Fortify (auth, 2FA, passkeys) · Wayfinder |
| Frontend | **React 19** + **Inertia.js 3** · TypeScript · Tailwind 4 · Radix UI · Recharts 3 · lucide · sonner |
| Build | Vite 8 |
| BD | SQLite (desarrollo) / **MySQL 8** (producción) |
| Calidad | PHPUnit (**299 tests**) · PHPStan/Larastan nivel 7 · Pint · ESLint · Prettier · GitHub Actions (lint + tests en PHP 8.3/8.4/8.5) |
| Repo | `github.com/ZapataMor/app-costeo` — **PÚBLICO** ⚠️ (ver [[cliente-y-negocio#Confidencialidad]]) |

Servidores locales definidos en `.claude/launch.json`: `app-costeo` (`php artisan serve --port=8801`) y `vite` (`npm run dev`, puerto 5173). Sembrado de datos: `php artisan migrate:fresh --seed` (dos hospitales ficticios marcados `[SEMILLA]`; la cesárea de referencia da exactamente **$520.000 COP**).

## Módulos principales (según el código, 2026-09-15)

```
app/
├── Models/            14 entidades de dominio + User + PlantillaX + AlertaSobrecosto + RegistroActividad
│   └── Concerns/BelongsToHospital + Scopes/HospitalScope   ← multi-tenant por hospital_id
├── Services/
│   ├── Costing/TdabcCostingService     motor de costeo (directo + indirecto por factor o por bolsas)
│   ├── Costing/AsignadorCif            reparto de bolsas CIF por inductor, snapshot, mayor resto
│   ├── Costing/ActivarCategoriaCif     único escritor del par activo + origen_* (anti-doble-conteo)
│   ├── Costing/OutlierDetector         z-score + Tukey por procedimiento
│   ├── Costing/DetectorSobrecostos     alertas de sobrecosto con causa y estado
│   ├── Indicators/KpiService           KPIs Donabedian (costos, CV, margen, utilización, glosas, completitud)
│   ├── Indicators/PersonalCosteoService costo y gasto movilizado por persona
│   ├── Cirugias/RegistrarCirugia, ActualizarCirugia, PresentarCirugiaDetalle
│   └── Plantillas/GeneradorPlantilla   sugiere la plantilla del protocolo desde el histórico
├── Http/Controllers/
│   ├── Parametros/*    CRUD por hospital: recursos humanos, insumos, equipos, salas, procedimientos,
│   │                   plantillas de protocolo, costos indirectos (bolsas CIF), configuración del hospital
│   ├── Cirugias/*      registro (ciclo pre → quirúrgica → post), cierre, facturación, resultado clínico
│   ├── Costeo/*        explorador por procedimiento, costeo por persona, bandeja de alertas
│   ├── Api/V1/*        endpoints JSON de KPIs y captura
│   ├── DashboardController (super_admin), DashboardCosteoController (4 tableros), HistorialController (auditoría),
│   │   DigitadorController, ExportacionController (CSV), HospitalActivoController (switcher)
│   └── Middleware: SetHospitalContext, EnsureRole, EnsureUsuarioActivo
├── Enums/             RolUsuario (super_admin | admin_hospital | digitador), EstadoCirugia, FaseCiclo,
│                      RolQuirurgico, CategoriaCif, BaseAsignacionCif, OrigenComponente, CausaSobrecosto…
└── Console/Commands/  DetectarSobrecostos, GenerarPlantillas
```

**Frontend** (`resources/js/pages/`): `cirugias/` (inicio, create, edit, index, show), `costeo/` (index, componentes, outliers, rentabilidad, variabilidad, alertas, personal/, procedimientos/), `parametros/` (hospital, recursos-humanos, insumos, equipos-medicos, salas-operatorias, procedimientos + plantilla, costos-indirectos), `pacientes/`, `digitadores/`, `historial/`, `dashboard`, `auth/`, `settings/`.

**Roles**: `super_admin` (todos los hospitales, switcher, dashboard consolidado) · `admin_hospital` (parámetros, costeo, digitadores de su hospital) · `digitador` (solo registra cirugías y corrige las suyas).

## Mapa contra la arquitectura de la tesis (Kerschberg, 5 capas)

| Capa | Estado |
|---|---|
| 1 Objetos (repositorio documental, protocolos) | ❌ No iniciada. *Nota: el README y las rutas llaman "Capa 1" a los parámetros del hospital, que en rigor son captura.* |
| 2 Datos (captura estructurada) | ✅ Completa: modelo multi-hospital, validaciones, ciclo por fases, plantillas de protocolo, trazabilidad del dato (`fuente`, `nivel_confiabilidad`) |
| 3 Información/BI | ✅ Completa: motor TDABC + CIF por bolsas, outliers, KPIs Donabedian, 4 dashboards + explorador por procedimiento y por persona, exportación CSV |
| 4 Conocimiento (reglas, alertas, lecciones aprendidas) | 🟡 Parcial: alertas de sobrecosto con causa y revisión. Faltan lecciones aprendidas, comunidades de práctica, recomendaciones |
| 5 Presentación (portales por usuario) | 🟡 Parcial: 3 roles y vistas por rol. Faltan portal del cirujano (benchmarking anónimo), portal ejecutivo consolidado, portal público, puesta en producción |

## Estado actual (2026-09-15)

- Último cambio de código: **2026-09-07** — motor de asignación de bolsas CIF completo ([[2026-09-04-cif-bolsas-vs-factor-vias-excluyentes]], [[2026-09-04-anti-doble-conteo-marcar-origen]]). Documentación técnica en `docs/cif-implementacion.md` y `docs/cif-vacios-diseno.md`.
- Tests: 299 pasando (según `docs/cif-implementacion.md`; el README aún dice 61 — desactualizado). [VERIFICAR] ejecutando `php artisan test` tras `npm run build`.
- Sin despliegue en producción [VERIFICAR]. Sin datos reales de hospitales aún [VERIFICAR]: los seeders son ficticios.
- La cotización de julio 2026 al profesor cubre capas 1-5; capas 4 y 5 quedaron "por desarrollar" ([[cliente-y-negocio#Acuerdo comercial]]). [VERIFICAR] si fue aceptada y si se recibió el anticipo.
- Visita institucional al hospital con cuestionario de 50 preguntas prevista para el **2026-07-22** ([[preguntas-levantamiento-hospital]]). [VERIFICAR] si ocurrió y qué se respondió — nada de eso está registrado en el cerebro.
- Este cerebro se creó el 2026-09-15 migrando el vault anterior y las dos carpetas de contexto del repo ([[2026-09-15]]). Todos los documentos recibidos tienen nota de fuente; los originales viven en `cerebro/otros/fuentes/originales/` ([[originales/README|índice]]).

## Próximos pasos

1. Confirmar con el cliente el estado del contrato y del levantamiento en los hospitales (ver [VERIFICAR] arriba).
2. Resolver las preguntas abiertas del CIF con el profesor ([[preguntas-abiertas]]): de dónde sale el monto mensual de cada bolsa, si el factor de ineficiencia institucional (1,15–1,28) entra al aplicativo.
3. Atacar el [[backlog-mejoras]] priorizado: alinear umbrales de desviación con la tesis, horas extra, capacidad no utilizada como reporte, portal del cirujano.
4. Capa 4 y 5 de la tesis (lecciones aprendidas, portales por usuario, producción).
5. Correr `analiza con los agentes` (ver `CLAUDE.md`) para poblar el backlog con hallazgos de los siete roles en [[agentes/arquitecto|agentes/]].

## Mapa del cerebro

- [[cliente-y-negocio]] — quién es el cliente, la tesis, el alcance confirmado, el acuerdo comercial.
- [[backlog-mejoras]] — mejoras numeradas con agente proponente y prioridad.
- `decisiones/` — una nota por decisión técnica (índice en [[decisiones/README|decisiones]]).
- `bitacora/` — una nota por fecha con los cambios (índice en [[bitacora/README|bitácora]]).
- `agentes/` — [[arquitecto]] · [[qa-seguridad]] · [[ux-ui]] · [[analista-negocio]] · [[experto-procesos-hospitalarios]] · [[experto-contabilidad-costos]] · [[experto-procedimientos-medicos]]
- `otros/` — conocimiento de dominio: [[otros/README|conceptos, actores, fuentes]] (14 notas de fuente + originales), [[preguntas-abiertas]], [[preguntas-levantamiento-hospital]], [[bandeja-de-entrada]].

### Conceptos clave (atajos)
[[Costeo ABC y TDABC]] ⭐ · [[Costos Indirectos (CIF)]] ⭐ · [[Sistema de Gestion del Conocimiento (SGC)]] ⭐ · [[Cotizacion de Procedimientos]] · [[Cuenta del Paciente]] · [[Complicaciones y Escalamiento de Costos]] · [[Sistema de Salud Colombiano]] · [[Glosario]]

### Fuentes clave (atajos)
[[Fuente - Capitulo 5 Tesis Doctoral SGC]] ⭐ · [[Fuente - Guia de Campos Base de Datos TDABC+MILP]] ⭐ · [[Fuente - Fundamentacion Optimizacion Costos Quirurgicos La Guajira]] · [[Fuente - Base de Datos 3100 Cirugias TDABC+MILP]] · [[Fuente - Libro Costeo ABC Hospital San Jose de Maicao]] · [[Fuente - Tablas Excel Recoleccion Costos Quirurgicos]] · [[Fuente - Ebook TDABC (Kaplan y Anderson)]] · [[Fuente - Articulo Kerschberg Knowledge Management Data Warehouse]]
