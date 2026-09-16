---
tags: [fuente, sgc, arquitectura, literatura]
origen: "originales/literatura/Kerschberg_2001_Knowledge_Management_Heterogeneous_Data_Warehouse.pdf (11 págs.; recibido como 'articulo modelo de LARRY KERSCHBERG.pdf')"
procesado: 2026-09-15
---

# Fuente — Kerschberg (2001), *Knowledge Management in Heterogeneous Data Warehouse Environments*

**Autor:** Larry Kerschberg, George Mason University. Ponencia en *Lecture Notes in Computer Science* (DaWaK 2001), DOI 10.1007/3-540-44801-2_1. Es el **artículo original del "modelo de Kerschberg"** que la tesis de [[cliente-y-negocio|Orlando Ruiz]] usa como arquitectura del [[Sistema de Gestion del Conocimiento (SGC)]].

> 🔑 Importante para no citar mal: el artículo propone una arquitectura de **tres capas** (presentación, gestión del conocimiento, fuentes de datos) y un **modelo de proceso de cinco actividades** (adquisición, refinamiento, almacenamiento/recuperación, distribución, presentación). Las "cinco capas" de la tesis (objetos, datos, información, conocimiento, presentación) son una **adaptación del profesor**, no la nomenclatura literal de Kerschberg. La *Fundamentación* usa incluso una tercera enumeración (fuentes, adquisición, representación, procesamiento, distribución). Cuando el aplicativo diga "Capa N", debe aclarar que sigue la numeración de la tesis.

## Resumen

### Punto de partida
Los data warehouses centralizados fracasan por heterogeneidad semántica y porque cada unidad quiere ser dueña de sus datos → tendencia a **warehouses federados** (data marts integrados por un hub de metadatos; caso Prudential). Además, la e-empresa reparte los datos entre socios (CRM, ERP, portales), lo que exige acuerdos de compartición, protocolos, seguridad y **estándares de calidad de datos**.

### Arquitectura de gestión del conocimiento (fig. 2 — tres capas)
1. **Capa de presentación del conocimiento**: portal de conocimiento personalizado por perfil de usuario; comunicación, colaboración y compartición entre trabajadores del conocimiento.
2. **Capa de gestión del conocimiento**: el **repositorio de conocimiento** y los procesos que lo crean y mantienen.
3. **Capa de fuentes de datos**: repositorios internos (documentos, correo, web, medios, **repositorio de dominio** con modelo y ontología, bases relacionales) y fuentes externas.

Roles humanos explícitos: **facilitadores, curadores e ingenieros del conocimiento**; estructuras e incentivos organizacionales para una organización que aprende.

### Modelo de proceso (sección 3.1 — cinco actividades)
- **Adquisición**: capturar conocimiento de expertos (entrevistas, casos) → reglas, heurísticas, casos.
- **Refinamiento**: clasificar, indexar y crear metadatos (conceptos, relaciones, eventos), contexto de uso, **pedigrí del dato** (propiedad intelectual, calidad, **fiabilidad de la fuente**); minería para descubrir patrones y **detectar outliers**.
- **Almacenamiento y recuperación**: índices por concepto, palabra clave, autor, evento, lugar; **controles de acceso y políticas de seguridad**.
- **Distribución**: portal, mensajería, **suscripciones activas** con agentes que avisan de información relevante.
- **Presentación**: portal adaptado a cada usuario, con colaboración para combinar conocimiento tácito y explícito.

### Servicios del sistema (fig. 3)
Portal y búsqueda; colaboración y mensajería; creación de conocimiento (anotar, etiquetar, agrupar en colecciones); **servicios de integración de información** (warehouse, federación, agentes, seguridad, mediación); minería de datos; metaetiquetado (XML/RDF, Dublin Core); **ontología y taxonomía** (tesauro inteligente); curación; workflow; mediación temporal (conversión entre unidades de tiempo, calendarios fiscales).

## Aportes al cerebro
- Corrige la atribución: la tesis adapta a Kerschberg; el aplicativo debe documentar su "capa" con la numeración de la tesis y citar el artículo como origen ([[Sistema de Gestion del Conocimiento (SGC)]]).
- Varias ideas del artículo **ya están** en el aplicativo sin llamarse así: pedigrí del dato = `fuente` + `nivel_confiabilidad`; detección de outliers = `OutlierDetector`; controles de acceso = roles + scope por hospital; presentación por perfil = vistas por rol.
- Ideas que faltan y encajan en las capas 4-5 de la tesis: **suscripciones/alertas activas** (ya hay alertas de sobrecosto; faltan notificaciones por suscripción), **repositorio de dominio con taxonomía** (catálogo CUPS/CIE-10/ATC como ontología compartida entre hospitales), **federación** entre hospitales con acuerdos de compartición ([[backlog-mejoras]] #17), roles de **curador del conocimiento**.

## Citas útiles
> "The traditional notion of data warehouse is evolving into a federated warehouse augmented by a knowledge repository, together with a set of processes and services to support enterprise knowledge creation, refinement, indexing, dissemination and evolution."

> "Data pedigree information is also added to the metadata descriptors, for example, intellectual property rights, data quality, source reliability, etc."

Cita APA: Kerschberg, L. (2001). Knowledge Management in Heterogeneous Data Warehouse Environments. En *Data Warehousing and Knowledge Discovery (DaWaK 2001)*, LNCS 2114, pp. 1-10. Springer. https://doi.org/10.1007/3-540-44801-2_1
