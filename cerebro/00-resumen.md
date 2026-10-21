---
tags: [moc, resumen]
actualizado: 2026-10-21
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
| BD | **MySQL 8** (desarrollo local vía `.env` y producción) · SQLite en memoria solo para tests (`phpunit.xml`) |
| Calidad | PHPUnit (**357 tests**, 2026-10-21) · PHPStan/Larastan nivel 7 · Pint · ESLint · Prettier · GitHub Actions (lint + tests en PHP 8.3/8.4/8.5) |
| Repo | `github.com/ZapataMor/app-costeo` — **PÚBLICO** ⚠️ (ver [[cliente-y-negocio#Confidencialidad]]) |

Servidores locales definidos en `.claude/launch.json`: `app-costeo` (`php artisan serve --port=8801`) y `vite` (`npm run dev`, puerto 5173). Sembrado de datos: `php artisan migrate:fresh --seed` (dos hospitales ficticios marcados `[SEMILLA]`; la cesárea de referencia da exactamente **$520.000 COP**). Carga de volumen opcional de la Capa 1 para San Rafael: `php artisan db:seed --class=SanRafaelCargaSeeder` y luego `--class=SanRafaelMovimientoSeeder` (2.169 cirugías abr–oct 2026 costeadas, bolsas CIF desde el 1 de julio; [[2026-10-19]]).

## Módulos principales (según el código, 2026-09-15)

```
app/
├── Models/            15 entidades de dominio + User + Plantilla{Personal,Insumo,Equipo} + AlertaSobrecosto + RegistroActividad
│   └── Concerns/BelongsToHospital + Scopes/HospitalScope   ← multi-tenant por hospital_id · Concerns/Auditable ← historial
│       (entidades: Hospital, Cirugia, Paciente, ProcedimientoQuirurgico, RecursoHumano, Insumo, EquipoMedico, SalaOperatoria,
│        MiembroEquipoQuirurgico, ConsumoInsumo, CostoCirugia, Facturacion, ResultadoClinico, ConceptoCostoIndirecto, CirugiaConceptoIndirecto)
├── Services/
│   ├── Costing/TdabcCostingService     motor de costeo (directo + indirecto por factor o por bolsas)
│   ├── Costing/AsignadorCif            reparto de bolsas CIF por inductor, snapshot, mayor resto
│   ├── Costing/ActivarCategoriaCif / DesactivarCategoriaCif   únicos escritores del par activo + origen_* (anti-doble-conteo)
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
│   ├── Pacientes/*     CRUD de pacientes · Settings/* perfil y seguridad (Fortify) · Concerns/FiltraListado (filtros comunes)
│   ├── Api/V1/*        endpoints JSON de KPIs y captura
│   ├── DashboardController (super_admin), DashboardCosteoController (4 tableros), HistorialController (auditoría),
│   │   DigitadorController, ExportacionController (CSV), HospitalActivoController (switcher)
│   └── Middleware: SetHospitalContext, EnsureRole, EnsureUsuarioActivo
├── Enums/             RolUsuario (super_admin | admin_hospital | digitador), EstadoCirugia, FaseCiclo,
│                      RolQuirurgico, CategoriaCif, BaseAsignacionCif, OrigenComponente, CausaSobrecosto…
└── Console/Commands/  DetectarSobrecostos, GenerarPlantillasCommand
```

**Frontend** (`resources/js/pages/`): `cirugias/` (inicio, create, edit, index, show), `costeo/` (index, componentes, outliers, rentabilidad, variabilidad, alertas, personal/, procedimientos/), `parametros/` (hospital, recursos-humanos, insumos, equipos-medicos, salas-operatorias, procedimientos + plantilla, costos-indirectos), `pacientes/`, `digitadores/`, `historial/`, `dashboard`, `auth/`, `settings/`.

**Roles**: `super_admin` (todos los hospitales, switcher, dashboard consolidado) · `admin_hospital` (parámetros, costeo, digitadores y profesionales de su hospital; curador del conocimiento) · `digitador` (solo registra cirugías y corrige las suyas) · `profesional` (personal quirúrgico atado a su recurso humano: lecciones, comunidades y mejores prácticas, sin costos ajenos).

**Capa 4 — Conocimiento** (`app/Services/Conocimiento/`, `app/Http/Controllers/Conocimiento/`, `resources/js/pages/conocimiento/`): `CatalogoReglas` + `MotorReglas` (8 reglas por cirugía y por mes, alertas con impacto en COP), `GestorLecciones` (borrador → revisión → publicada, red de hospitales), `GeneradorRecomendaciones` (6 tipos con ahorro mensual estimado), `AnalizadorMejoresPracticas` (mejor desempeño y benchmarking anónimo), `GestorComunidades` (foros por especialidad), `TableroConocimiento`. Costo estándar vs. real en `Costing/CalculadorCostoEstandar` con clasificación de desviación por hospital. Diseño en [[2026-10-21-capa-4-conocimiento]].

## Mapa contra la arquitectura de la tesis (Kerschberg, 5 capas)

| Capa | Estado |
|---|---|
| 1 Objetos (repositorio documental, protocolos) | ❌ No iniciada. *Nota: el README y las rutas llaman "Capa 1" a los parámetros del hospital, que en rigor son captura.* |
| 2 Datos (captura estructurada) | ✅ Completa: modelo multi-hospital, validaciones, ciclo por fases, plantillas de protocolo, trazabilidad del dato (`fuente`, `nivel_confiabilidad`) |
| 3 Información/BI | ✅ Completa: motor TDABC + CIF por bolsas, outliers, KPIs Donabedian, 4 dashboards + explorador por procedimiento y por persona, exportación CSV |
| 4 Conocimiento (reglas, alertas, lecciones aprendidas) | ✅ Completa (2026-10-21): costo estándar vs. real con clasificación −5/5/20 %, reglas configurables con alertas valoradas en COP, lecciones aprendidas con validación y red de hospitales, recomendaciones con ahorro estimado y seguimiento, mejores prácticas derivadas de datos con benchmarking anónimo, comunidades de práctica por especialidad, tablero contra la meta de la tesis. Pendiente: notificaciones (#24) y evaluación programada (#25) |
| 5 Presentación (portales por usuario) | 🟡 Parcial: 4 roles y vistas por rol (el profesional ya tiene cuenta y ve su fila marcada en el benchmarking). Faltan portal del cirujano (benchmarking anónimo), portal ejecutivo consolidado, portal público, puesta en producción |

## Estado actual (2026-10-21)

- Último cambio de código: **2026-10-21** — Capa 4 completa ([[2026-10-21]], [[2026-10-21-capa-4-conocimiento]]); antes, PHPStan en verde y costo estándar vs. real (#1, #3). Carga de volumen de San Rafael en [[2026-10-19]].
- Tests: **357 pasando, 2.240 aserciones** — verificado el 2026-10-21 ejecutando `php artisan test`. PHPStan nivel 7, Pint, ESLint, Prettier y `tsc` en verde.
- Datos de prueba de San Rafael: `SanRafaelCargaSeeder` → `SanRafaelMovimientoSeeder` → `SanRafaelConocimientoSeeder` (12 profesionales, 22 lecciones, 9 comunidades, 737 alertas de reglas, 41 recomendaciones).
- Sin despliegue en producción [VERIFICAR]. Sin datos reales de hospitales aún [VERIFICAR]: los seeders son ficticios.
- La cotización de julio 2026 al profesor cubre capas 1-5; capas 4 y 5 quedaron "por desarrollar" ([[cliente-y-negocio#Acuerdo comercial]]). [VERIFICAR] si fue aceptada y si se recibió el anticipo.
- Visita institucional al hospital con cuestionario de 50 preguntas prevista para el **2026-07-22** ([[preguntas-levantamiento-hospital]]). [VERIFICAR] si ocurrió y qué se respondió — nada de eso está registrado en el cerebro.
- Este cerebro se creó el 2026-09-15 migrando el vault anterior y las dos carpetas de contexto del repo ([[2026-09-15]]). Todos los documentos recibidos tienen nota de fuente; los originales viven en `cerebro/otros/fuentes/originales/` ([[originales/README|índice]]).

## Próximos pasos

1. Confirmar con el cliente el estado del contrato y del levantamiento en los hospitales (ver [VERIFICAR] arriba).
2. Resolver las preguntas abiertas del CIF con el profesor ([[preguntas-abiertas]]): de dónde sale el monto mensual de cada bolsa, si el factor de ineficiencia institucional (1,15–1,28) entra al aplicativo.
3. Atacar el [[backlog-mejoras]] priorizado: insumos facturables aparte (#22), horas extra, capacidad no utilizada como reporte, portal del cirujano.
4. Capa 5 de la tesis (portal del cirujano sobre el rol profesional, portal ejecutivo, portal público, producción) y separar los implantes facturables aparte (#22) antes de presentar márgenes de ortopedia.
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
