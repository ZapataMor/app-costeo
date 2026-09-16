# CLAUDE.md — app-costeo (SICOPH)

Aplicativo Laravel 13 + Inertia + React 19 de costeo quirúrgico TDABC multi-hospital. El conocimiento del proyecto vive en `cerebro/` (vault de Obsidian versionado con el código). Este archivo define cómo trabajar con él.

## Antes de cualquier tarea

1. Leer `cerebro/00-resumen.md` (qué es, stack, módulos, estado actual, próximos pasos).
2. Leer `cerebro/backlog-mejoras.md` (mejoras numeradas con prioridad y estado).
3. Si la tarea toca el motor de costos, leer además `cerebro/otros/conceptos/Costos Indirectos (CIF).md` y `docs/cif-implementacion.md`.
4. Si la tarea toca el cliente, el alcance o el negocio, leer `cerebro/cliente-y-negocio.md`.

## Después de cambiar código

1. **Bitácora**: agregar (o ampliar) `cerebro/bitacora/AAAA-MM-DD.md` con la fecha de hoy: qué cambió, por qué, archivos clave, tests, pendientes. Añadir la fila en `cerebro/bitacora/README.md`.
2. **Decisión**: si hubo una decisión técnica importante (arquitectura, modelo de datos, fórmula de costeo, seguridad, alcance), crear `cerebro/decisiones/AAAA-MM-DD-slug.md` con **contexto → decisión → por qué → consecuencias**, y añadirla a `cerebro/decisiones/README.md`. Si se revierte una decisión, no borrar la nota: añadir "Revertida el …" y enlazar la nueva.
3. **Estado**: si cambió el estado del proyecto (módulo nuevo, capa completada, nº de tests, despliegue, contrato), actualizar `cerebro/00-resumen.md` (secciones *Estado actual*, *Módulos*, *Mapa contra la arquitectura de la tesis*) y su campo `actualizado`.
4. Si el cambio responde a una pregunta de `cerebro/otros/preguntas-abiertas.md`, moverla a respondidas.

## Comandos del cerebro

### "analiza con los agentes"
1. Leer cada archivo de `cerebro/agentes/*.md` (excepto `README.md`).
2. Para cada agente, analizar el proyecto (código, `docs/`, cerebro) desde ese rol, respondiendo sus "preguntas que siempre hace" y usando su formato **hallazgo → impacto → mejora propuesta → prioridad**.
3. Agregar cada mejora nueva al final de la tabla de `cerebro/backlog-mejoras.md` con el número siguiente, el agente que la propuso y la prioridad. **No renumerar** ni duplicar lo ya listado; si un hallazgo refuerza una mejora existente, anotarlo en esa fila.
4. Registrar la corrida en la bitácora del día con el resumen de hallazgos por agente.
5. Se puede acotar: "analiza con el agente qa-seguridad" o "analiza con los agentes de dominio" (los tres `experto-*`).

### "implementa la mejora #N"
1. Tomar la fila #N de `cerebro/backlog-mejoras.md`; leer las notas que enlaza.
2. Implementarla con tests; correr `npm run build && php artisan test` (las páginas Inertia necesitan el manifiesto de Vite) y `composer lint` / `composer types:check`.
3. Marcar la fila como `✅ hecha` con enlace a la nota de bitácora (`[[AAAA-MM-DD]]`) y moverla a la sección **Hechas** del backlog.
4. Aplicar las reglas de *Después de cambiar código*.

### "procesa los documentos nuevos de contexto"
Seguir `cerebro/otros/bandeja-de-entrada.md`.

## Convenciones del cerebro

- Markdown compatible con Obsidian; enlaces con `[[nombre-de-nota]]` (sin ruta ni extensión). Los nombres de archivo de `cerebro/otros/` tienen espacios y paréntesis heredados: no renombrarlos sin actualizar todos los enlaces.
- Fechas absolutas `AAAA-MM-DD`. Marcar con `[VERIFICAR]` toda suposición no confirmada con el código, el cliente o el hospital.
- Cifras en COP; fórmulas del motor con nombres de columna reales.
- El cerebro contiene datos del cliente y de los hospitales; el repositorio remoto es **público** (ver `cerebro/cliente-y-negocio.md` §11). No agregar datos personales de pacientes ni credenciales reales.
- No crear el junction de Obsidian (lo crea el usuario). Las carpetas `información-contexto/` y `Documentos contexto SICOPH/` fueron migradas a `cerebro/` y eliminadas el 2026-09-15; si reaparecen por un merge, su contenido ya está en `cerebro/otros/`.

## Convenciones del código (resumen)

- Multi-tenant por `hospital_id`: toda tabla de dominio usa el trait `BelongsToHospital`; las consultas SQL crudas filtran por hospital explícitamente.
- El motor de costeo lee los parámetros **congelados en la cirugía**, nunca el catálogo actual.
- `activo` de las bolsas CIF y `origen_*` del hospital solo se escriben desde `ActivarCategoriaCif` / `DesactivarCategoriaCif`.
- Repartos proporcionales con `AsignadorCif::repartirPorMayorResto()`; redondear una sola vez.
- Comentarios y UI en español; el caso de prueba obligatorio es la cesárea de $520.000 (`tests/Unit/TdabcCostingServiceTest.php`).
- Calidad: PHPStan nivel 7, Pint, ESLint, Prettier; CI en GitHub Actions (PHP 8.3/8.4/8.5).
