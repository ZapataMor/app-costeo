---
tags: [decision, documentacion, proceso]
fecha: 2026-09-15
estado: vigente
---

# El cerebro del proyecto vive en `cerebro/` dentro del repositorio

## Contexto
Existía un vault de Obsidian en `información-contexto/Cerebro/` (conceptos, actores, fuentes, preguntas), desconectado del código: los cambios de la app no se reflejaban en él ni sus ideas llegaban al backlog. El usuario trabaja en dos equipos y quiere una única copia real, versionada con Git, visible desde una bóveda central de Obsidian mediante junctions de Windows.

## Decisión
- `cerebro/` en la raíz del repo es la **única copia real**; la bóveda central la ve por `mklink /J`.
- Estructura fija: `00-resumen.md`, `cliente-y-negocio.md`, `backlog-mejoras.md`, `decisiones/`, `bitacora/`, `agentes/`, `otros/` (conocimiento de dominio migrado).
- `CLAUDE.md` en la raíz obliga a leer el resumen y el backlog antes de cualquier tarea, y a escribir bitácora/decisión/estado después de cambiar código.
- Los nombres de archivo de las notas migradas se conservaron para que los `[[enlaces]]` existentes sigan resolviendo.
- Siete agentes: cuatro genéricos (arquitecto, qa-seguridad, ux-ui, analista-negocio) y tres de dominio (procesos hospitalarios, contabilidad de costos, procedimientos médicos), porque el cliente pidió esas tres experticias.

## Por qué
- Una sola fuente de verdad, con historial, que viaja con el código y se sincroniza entre equipos con `git pull`.
- Las notas de fuente y los conceptos alimentan a los agentes de dominio; el backlog conecta las ideas con el código.

## Consecuencias
- ⚠️ El repositorio es **público**: el cerebro contiene datos del cliente y de los hospitales. Decisión pendiente del usuario ([[backlog-mejoras]] #15).
- Duplicación temporal: `información-contexto/Cerebro/` sigue existiendo hasta que el usuario confirme su borrado; `otros/fuentes/originales/` duplica casi todo `Documentos contexto SICOPH/`.
- Nombres de archivo con espacios y paréntesis en `otros/` (heredados) — se aceptan por compatibilidad de enlaces.
