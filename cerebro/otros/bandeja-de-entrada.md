---
tags: [flujo, inbox]
migrado_de:
  - "información-contexto/Cerebro/00 - Bandeja de Entrada/LEEME.md"
  - "información-contexto/Cerebro/INICIO.md (sección Flujo de trabajo del cerebro)"
---

# 📥 Bandeja de entrada — cómo entran documentos nuevos al cerebro

Los PDFs y documentos Word que entregue el profesor [[cliente-y-negocio|Orlando Ruiz]] (o que se encuentren sobre el tema) se dejan en `cerebro/otros/fuentes/originales/` en la subcarpeta que corresponda (`profesor/`, `literatura/` o `proyecto/`). Luego se le pide a Claude:

> "Procesa los documentos nuevos de contexto"

Claude hará lo siguiente:
1. Leer cada PDF/Word de `originales/` que **no aparezca todavía** en el índice [[originales/README|originales]] con una nota `Fuente - …`.
2. Crear una nota resumen por documento en `otros/fuentes/` con lo esencial (origen, resumen, aportes al cerebro, citas útiles) siguiendo la convención de [[otros/README|otros]].
3. Actualizar los conceptos afectados (`otros/conceptos/`) y los actores (`otros/actores/`).
4. Actualizar [[cliente-y-negocio]] y [[00-resumen]] con lo nuevo que se haya aprendido.
5. Mover preguntas resueltas de [[preguntas-abiertas]] a las notas correspondientes.
6. Si el documento cambia una fórmula o regla del motor de costos, agregar la mejora al [[backlog-mejoras]] con el agente [[experto-contabilidad-costos]].
7. Registrar el procesamiento en `bitacora/AAAA-MM-DD.md`.

Al terminar, añadir la fila del archivo en `originales/README.md`. Los archivos de texto extraíbles (Word) se leen con Python (`zipfile` + limpieza de XML); los PDF con `pypdf`; los Excel con `openpyxl`.
