---
tags: [decision, ux, presentacion, capa-5, alertas]
fecha: 2026-10-27
estado: vigente
---

# Portada del administrador y bandeja única de alertas

## Contexto
En la revisión visual del 2026-10-27 ([[2026-10-27]]) el aplicativo se sentía enredado: el `admin_hospital` entraba a *Parámetros* (configuración) y para saber cómo iba el mes tenía que recorrer cuatro tableros; los indicadores no decían si eran buenos o malos; y el menú tenía **dos contadores de alertas** (32 sobrecostos en *Costeo*, 252 críticas en *Alertas y reglas*) para dos pantallas casi iguales, con 455 alertas pendientes sueltas. Además, las vistas de costeo se encadenaban con siete tarjetas de enlace en medio de `/costeo`, y el detalle de una cirugía eran seis o siete tablas con el veredicto a mitad de página.

## Decisión

1. **Portada `/inicio`** (`InicioController` + `Indicators/ResumenHospital`) como destino del `admin_hospital` al iniciar sesión y en `/` (antes `/parametros`). Muestra cuatro indicadores del **último mes con cirugías realizadas** (no el mes calendario: la captura va por lotes y «este mes» puede estar vacío) frente al anterior: margen sobre lo facturado (`KpiService::resultado()`, nuevo), costo promedio, completitud y casos con alertas. Debajo, **como mucho cinco** cosas que piden acción, en orden fijo: alertas, procedimiento con mayor pérdida (últimos 3 meses, margen × n), cirugías sin completar, lecciones por validar, recomendaciones nuevas. El backend manda datos; el texto lo arma la página con el formato común de cifras.
2. **Bandeja única `/alertas`** (`AlertasController` + `Alertas/BandejaAlertas`): sobrecostos y alertas de las reglas **agrupados por procedimiento** (las de sala y del mes del hospital en un grupo «Salas y hospital»), con filtro por mes. Un caso es **prioritario** si tiene un sobrecosto sin explicar o una alerta crítica. Los montos de cada fuente **no se suman** entre sí (#27, #28). Cada caso abre la bandeja de su fuente filtrada (`?procedimiento=&mes=`), donde se sigue atribuyendo la causa; las bandejas de origen no cambian de comportamiento.
3. **Un solo contador en el menú** (`alertasPorRevisar`): casos prioritarios, no alertas sueltas. Las props `alertasPendientes` y `conocimientoPendientes` se conservan (tests y otros usos); el menú ya no las muestra como contador de alertas.
4. **Semáforo con umbrales en un solo lugar** (`resources/js/lib/semaforo.ts`): completitud ≥ 90 % (meta de la tesis, indicador 16 de la Fundamentación) / ≥ 75 %; margen ≥ 0 / ≥ −10 % [VERIFICAR]; variación del costo ≤ 0 / ≤ +5 % [VERIFICAR]. `KpiCard` acepta `tono` y `comparacion`. Glosas, recaudo y utilización quedan sin semáforo hasta tener meta.
5. **Pestañas de costeo** en `EncabezadoCosteo` (Resumen, Procedimientos, Rentabilidad, Composición del costo, Personal, Costos fuera de lo normal, Variabilidad), que conservan `desde`/`hasta`. Las alertas salieron del módulo: van a la bandeja.
6. **Detalle de cirugía**: resumen (costo, desviación frente al estándar, margen, estado) y barra de composición arriba; el desglose completo queda en `CalculoDetallado`, plegado, también en el explorador de costeo.

## Por qué
- La primera pantalla debe responder la pregunta del gerente («¿cómo vamos?»), no la del configurador.
- Contar casos por procedimiento convierte 455 alertas en ~42 filas que se pueden revisar en una sesión; sumar exceso e impacto daría una cifra que mezcla naturalezas y a veces cuenta dos veces la misma cirugía.
- Umbrales centralizados evitan que el mismo indicador sea verde en una pantalla y ámbar en otra.
- Mantener las bandejas de origen evita reescribir el flujo de revisión (causas, lecciones) que ya tiene pruebas.

## Consecuencias
- El administrador ya no aterriza en Parámetros; se llega por el menú (grupo *Configuración*, al final).
- La unificación es de presentación: `DetectorSobrecostos` y `MotorReglas` siguen generando alertas por separado; unificar el criterio sigue pendiente en #27.
- Los umbrales de margen y de variación del costo son propuestos, no confirmados con el cliente ([[preguntas-abiertas]]).
- El super_admin necesita un hospital elegido para ver `/inicio` y `/alertas` (`hospital.requerido`).
