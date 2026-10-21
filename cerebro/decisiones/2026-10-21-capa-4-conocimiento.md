---
tags: [decision, arquitectura, capa-4, conocimiento]
fecha: 2026-10-21
estado: vigente
---

# Capa 4 — Conocimiento: reglas, lecciones, recomendaciones, comunidades

## Contexto
La tesis (capítulo 5) define la capa 4 como **comunidades de práctica, lecciones aprendidas, mejores prácticas, minería de datos y gestión de protocolos**, y la cotización ata el segundo pago a «reglas, alertas, recomendaciones». Hasta el 2026-10-20 solo existía la bandeja de alertas de sobrecosto (estadística, con causa). Antes de construir la capa se cerraron dos prerrequisitos del backlog: el **costo estándar vs. real** (#3) y la **clasificación de desviación** del pipeline (#1), porque las reglas y recomendaciones necesitan un «debió costar X».

Decisiones del usuario (2026-10-20): cerrar primero los prerrequisitos, **nuevo rol profesional**, **lecciones compartibles con la red** de hospitales y **comunidades como foros enlazados** (sin chat ni calendario).

## Decisión

1. **Costo estándar** (`CalculadorCostoEstandar`) = la misma cirugía con las **tarifas congeladas del caso** pero con las cantidades del protocolo: duración estimada, plantilla de personal (tarifa promedio de quienes ocuparon el rol), equipos e insumos no opcionales; el indirecto por la misma vía (factor o bolsas). Se guarda en `costos_cirugia` con `desviacion`, `desviacion_pct` y `clasificacion_desviacion`. Sin protocolo no hay estándar (null), no se inventa.
2. **Clasificación** Subejecución ≤ −5 % · En rango · Sobrecosto leve 5–20 % · Sobrecosto alto > 20 %, con umbrales **por hospital** (`hospitales.umbral_*`). Cambiar los umbrales reclasifica el histórico sin recostear (`ReclasificadorDesviaciones`).
3. **Reglas de conocimiento** (`reglas_conocimiento`, una por tipo y hospital, creadas perezosamente con parámetros por defecto): tres de alcance **cirugía** (desviación vs. estándar, tiempo prolongado, consumo fuera de protocolo), evaluadas en el mismo punto que el detector de sobrecostos; cinco de alcance **mes cerrado** (margen negativo, sala subutilizada, tasa de complicaciones, cancelación, completitud). Cada hallazgo es una `alerta_conocimiento` con **impacto en COP** y **clave estable** (tipo + objeto + mes): reevaluar actualiza la pendiente, nunca pisa una revisada y retira la pendiente cuyo patrón desapareció.
4. **Lecciones aprendidas** con ciclo **borrador → en revisión → publicada → archivada**. Solo el administrador del hospital (curador del conocimiento, en términos de Kerschberg) publica, devuelve, archiva o comparte con la red. Tipo `leccion` o `mejor_practica` en la misma tabla. Nacen prellenadas desde una alerta o un hilo.
5. **Red de hospitales**: una lección publicada puede compartirse; se lee desde cualquier hospital con `scopeDeLaRed()` (salta el scope del tenant a propósito) y por una ruta propia (`lecciones/red/{id}`) sin autor, cirugías ni alertas.
6. **Recomendaciones** (`GeneradorRecomendaciones`): minería **descriptiva** sobre los últimos 6 meses (estandarizar insumo, ajustar protocolo, renegociar tarifa, reducir tiempo, reasignar capacidad, revisión entre pares), cada una con **ahorro mensual estimado** y evidencia. Solo se reescriben las `nuevas`; gestionadas conservan la evidencia; las nuevas que el histórico ya no sostiene se retiran. Seguimiento: aceptada (responsable + fecha) → implementada, o descartada (motivo).
7. **Mejores prácticas** derivadas de datos: «mejor desempeño» = cuartil de menor costo **sin complicaciones** y con estancia ≤ mediana; comparación por componente e insumo; **benchmarking de profesionales anónimo** (letras), con la fila propia marcada para el profesional. El profesional no ve pesos.
8. **Comunidades de práctica**: una general y una por especialidad del portafolio; membresía **derivada** del recurso humano (anestesia y enfermería quirúrgica entran a todas; el admin modera). Hilos enlazables a lección, alerta, procedimiento o recomendación; solución marcada; conversión del hilo en lección.
9. **Rol `profesional`**: usuario atado a un `recurso_humano_id` (uno por registro). Ve lecciones, comunidades y mejores prácticas; no ve costeo, parámetros ni gestión.

## Por qué
- El costo estándar separa uso de precio: la desviación mide lo gestionable.
- Las claves estables y el «no pisar lo revisado» hacen que reevaluar sea seguro: la decisión de una persona vale más que la recomputación.
- El impacto en COP ordena la bandeja por dinero, que es como decide un comité.
- Validación obligatoria antes de publicar: un repositorio que se llena de opiniones deja de consultarse.
- Anonimato y enfoque no punitivo: lo pide la tesis para el portal del cirujano.

## Consecuencias
- Al activar la capa en un hospital con histórico hay que correr `php artisan costeo:recalcular` (estándar) y «Evaluar el histórico» (reglas).
- La regla de margen negativo y la recomendación de renegociar tarifa marcan la **ortopedia con implantes** como deficitaria mientras el implante no se separe como insumo facturable aparte ([[backlog-mejoras]] #22).
- La meta de la tesis (de 2 a 10 lecciones/mes) queda medida en el tablero.
- Pendientes de capa 5 que esta decisión prepara: portal individual del cirujano (#9) sobre el rol profesional, red departamental (#17) sobre las lecciones compartidas.
