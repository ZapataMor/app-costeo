---
tags: [moc, bitacora]
---

# Bitácora

Una nota por fecha (`AAAA-MM-DD.md`) con **qué cambió, por qué y qué queda pendiente**. Las entradas anteriores al 2026-09-15 se reconstruyeron del historial de Git y de las notas del vault anterior; son resúmenes, no registros contemporáneos.

| Fecha | Resumen |
|---|---|
| [[2026-07-05]] | Llegan los documentos del profesor; nace el vault de conocimiento |
| [[2026-07-06]] | Primer commit: modelo de datos, motor TDABC, KPIs, dashboards (capas 2 y 3) |
| [[2026-07-11]] | Literatura externa procesada; contexto de hospital, super_admin y trazabilidad del dato |
| [[2026-07-12]] | Dashboard por hospital, CRUD de cirugías y pacientes, parámetros con catálogo |
| [[2026-07-13]] | Auditoría (historial de actividad), autorización del costeo, snapshots, identidad visual |
| [[2026-07-20]] | Rol digitador; se elimina la página de bienvenida |
| [[2026-07-21]] | Ciclo por fases, horas por miembro, filtros por periodo, exportación CSV, rebranding a SICOPH |
| [[2026-07-22]] | Alertas de sobrecosto, tendencias, seeders por dominio; cotización al profesor y cuestionario de visita |
| [[2026-09-03]] | Capacidad práctica configurable (`minutos_efectivos_hora`) |
| [[2026-09-04]] | Catálogo de bolsas CIF y mecanismo anti-doble-conteo |
| [[2026-09-06]] | Guía TDABC+MILP procesada; vault subido al repo; diseño del motor CIF |
| [[2026-09-07]] | Motor de asignación CIF completo; 299 tests |
| [[2026-09-15]] | Creación del cerebro en `cerebro/` y `CLAUDE.md`; migración del vault anterior; auditoría de la organización y traslado de la bóveda central a `C:\Obsidian\Cerebros` |
| [[2026-09-19]] | Documentación técnica completa del aplicativo en `docs/SICOPH-Documentacion-Tecnica.pdf` |
| [[2026-09-23]] | Diagrama de flujo de las cinco capas de Kerschberg y diagrama de casos de uso con el movimiento de los datos (`docs/SICOPH-Capas-Kerschberg-Flujo.pdf`, `docs/SICOPH-Casos-de-Uso-Datos.pdf`) |
| [[2026-10-19]] | Carga de volumen en San Rafael: Capa 1 (`SanRafaelCargaSeeder`: 5 salas, 62 personas, 206 insumos, 61 equipos, 52 procedimientos, 12 bolsas CIF) y movimiento abr–oct 2026 (`SanRafaelMovimientoSeeder`: 2.169 cirugías costeadas, transición a bolsas CIF el 1 de julio) |
| [[2026-10-21]] | Capa 4 completa: PHPStan en verde, costo estándar vs. real y clasificación de desviación (#1, #3), reglas con alertas valoradas, lecciones con validación y red, recomendaciones con ahorro, mejores prácticas, comunidades, rol profesional; 357 tests |
| [[2026-10-27]] | Análisis de los datos de prueba de San Rafael (recarga de los tres seeders): márgenes por especialidad, subsidio cruzado de la depreciación por minuto de quirófano, capacidad ociosa, ruido de alertas, ahorro mezclado; mejoras #26–#29. Tarde: revisión visual, recomendaciones #30–#39 y cambios rápidos de presentación hechos (#36 lenguaje, #37 tabla de cirugías, #38 jerarquía y contraste). Noche: portada del administrador, bandeja única de alertas, pestañas de costeo, detalle de cirugía con resumen y rentabilidad en tabla (#30–#35); 368 tests. Revisión por módulos: hub de Parámetros minimalista (solo conteo y «Añadir», card clicable); barra superior vacía salvo el tema y sidebar que se recoge con el ícono de la app; 369 tests |
