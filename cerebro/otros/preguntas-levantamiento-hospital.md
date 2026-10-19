---
tags: [instrumento, levantamiento, hospitales]
migrado_de: "información-contexto/Preguntas_Levantamiento_Hospital.pdf"
fecha_visita: 2026-07-22
---

# Preguntas para el levantamiento de información — visita institucional al hospital

Cuestionario de 50 preguntas preparado para la visita del **22 de julio de 2026** (aplicativo de costeo de procedimientos quirúrgicos por tiempo, TDABC). [VERIFICAR] si la visita ocurrió y registrar las respuestas aquí mismo, bajo cada pregunta, o en `bitacora/`.

Los agentes [[experto-procesos-hospitalarios]], [[experto-contabilidad-costos]] y [[experto-procedimientos-medicos]] usan este cuestionario como lista de verificación de lo que el aplicativo aún supone sin confirmar.

## Bloque 1. Captura del dato en el procedimiento quirúrgico
*El costeo TDABC se construye sobre los minutos reales de cada procedimiento.*
1. Hoy, cuando se realiza una cirugía, ¿quién registra los tiempos y en qué momento lo hace? (instrumentadora, circulante, anestesia, secretaría de quirófano)
2. ¿Qué marcas de tiempo registran exactamente: entrada del paciente a sala, inicio de anestesia, incisión, cierre, salida de sala, ingreso a recuperación? ¿Cuáles son obligatorias y cuáles se diligencian después?
3. ¿El registro se hace en papel o directamente en un sistema? Si es en papel, ¿en qué momento y quién lo digita?
4. ¿Existe un reloj o pantalla de referencia dentro de la sala? ¿Los relojes de los distintos servicios están sincronizados?
5. Cuando una cirugía se complica o se convierte (por ejemplo, de laparoscópica a abierta), ¿cómo queda registrado ese cambio?
6. ¿Cómo se registran los tiempos no quirúrgicos: alistamiento y aseo de sala entre cirugías, esperas por el paciente, cancelaciones de última hora? ¿Alguien los mide hoy?
7. ¿Cuánto tiempo adicional por cirugía estaría dispuesto a invertir el personal de sala para registrar información en la aplicación?
8. ¿Se cuenta con conectividad estable y algún dispositivo dentro de la sala (tablet, computador, lector de código de barras), o el registro tendría que hacerse fuera de sala?

## Bloque 2. Insumos, medicamentos y dispositivos
9. ¿Cómo se despachan los insumos a quirófano: kit o paquete quirúrgico predefinido, pedido por cirugía, o carro de sala?
10. ¿Cómo se registra el consumo real frente a lo devuelto? ¿Lo no utilizado regresa a farmacia y queda soporte de esa devolución?
11. ¿Los insumos cuentan con código de barras y hay lectores disponibles? ¿Manejan código ATC en medicamentos, código CUM o codificación propia?
12. ¿Qué valor se toma como costo unitario del insumo: precio de compra, promedio ponderado o tarifa de venta? ¿Con qué periodicidad se actualiza?
13. ¿Cómo se manejan los implantes y el material de osteosíntesis en consignación, así como los equipos aportados por el proveedor? ¿Se incorporan al costo del procedimiento?
14. ¿Cómo se registran el desperdicio, el insumo abierto y no utilizado, y las devoluciones por vencimiento?

## Bloque 3. Talento humano y costo por minuto
15. ¿Bajo qué modalidad está vinculado el personal quirúrgico: planta, prestación de servicios, agremiación u honorarios por procedimiento?
16. ¿El cirujano se remunera por procedimiento (tarifa fija) o por tiempo? ¿Cuál es el esquema para anestesiología?
17. ¿Cuántos minutos disponibles reales estiman por profesional al mes, descontando vacaciones, incapacidades, reuniones y capacitación?
18. ¿La información de costo laboral puede entregarse por cargo o rol promedio, o únicamente de forma agregada? ¿Existe alguna restricción por protección de datos o acuerdo sindical?
19. ¿Quién dentro del hospital tendría la autoridad para cargar y aprobar los costos por minuto de cada rol?

## Bloque 4. Salas, equipos y costos indirectos
20. ¿Cómo se calcula hoy el costo por hora de quirófano? ¿Ese indicador ya existe o habría que construirlo?
21. ¿Cuál es la tasa real de utilización de las salas: horas programadas frente a horas efectivamente operadas al mes?
22. ¿Cómo se distribuyen actualmente los costos indirectos (administración, servicios públicos, esterilización, lavandería, mantenimiento, depreciación)? ¿Por número de cirugías, por metros cuadrados, por horas de uso?
23. ¿La central de esterilización factura o imputa costos a quirófano? ¿Cómo se costea un paquete estéril?
24. ¿Existe inventario de equipos médicos con valor de compra, vida útil y contrato de mantenimiento asociado?

## Bloque 5. Sistemas de información e integración
*Dirigidas principalmente al equipo de tecnología del hospital.*
25. ¿Qué sistema de información hospitalario (HIS) y qué ERP utilizan? ¿Qué versión y qué proveedor?
26. ¿El sistema expone API, permite consulta directa a base de datos, o únicamente genera reportes en Excel o PDF? ¿El proveedor autoriza ese acceso?
27. ¿En qué sistema reside hoy cada dato: agenda quirúrgica, historia clínica, farmacia e inventario, nómina y facturación? ¿Es un solo sistema o son módulos independientes?
28. ¿Cuál es el identificador único de una cirugía en su sistema? ¿Es posible enlazar nuestros registros con ese identificador?
29. ¿Prefieren que la aplicación importe archivos planos de forma periódica o que se integre en línea con sus sistemas?
30. En cuanto a infraestructura, ¿aceptan despliegue en la nube o exigen servidor local? ¿Qué exige su política de seguridad de la información?
31. ¿Cómo gestionan hoy la autenticación de usuarios? ¿Cuentan con directorio activo o inicio de sesión único (SSO) al que debamos integrarnos?
32. ¿Qué exige su política de historia clínica y el cumplimiento de la Ley 1581 de 2012? ¿Podemos almacenar la identificación del paciente o debe anonimizarse?

## Bloque 6. Protocolos y estandarización clínica
33. ¿Cuentan con protocolos escritos por procedimiento (guía de manejo, kit estándar, tiempo estimado)? ¿Sería posible revisar dos o tres como ejemplo?
34. ¿Con qué codificación identifican los procedimientos: CUPS, código interno o denominación libre del cirujano?
35. ¿Qué nivel de variación consideran normal entre dos cirujanos que realizan el mismo procedimiento?
36. ¿Existe un mapa de proceso del paciente quirúrgico, desde la consulta y programación hasta recuperación y alta?
37. ¿Qué procedimiento trazador recomiendan para una prueba piloto, idealmente de alto volumen y baja complejidad?

## Bloque 7. Decisión, valor y gobierno del sistema
*Dirigidas principalmente al nivel directivo.*
38. ¿Qué decisión concreta tomarían si contaran con el costo real por procedimiento? (negociación de tarifas, continuidad de un servicio, cambio de proveedor, ajustes contractuales con las EPS)
39. ¿Hoy pueden establecer si obtienen margen o pérdida en cada contrato con las aseguradoras? ¿Cómo lo estiman?
40. ¿Qué indicadores revisa el comité directivo cada mes? ¿La aplicación podría alimentarlos?
41. Cuando el sistema evidencie que un procedimiento cuesta más de lo que se factura, ¿quién debe ser notificado y en qué plazo?
42. ¿Qué áreas o actores podrían presentar resistencia a la implementación del sistema y por qué razones?
43. ¿Se han adelantado antes iniciativas de costeo en el hospital? ¿Cuáles fueron sus resultados y sus limitaciones?
44. ¿Qué dependencia sería la responsable del sistema: financiera, calidad, quirófano o planeación?
45. ¿Existen requerimientos de reporte externo que el sistema deba apoyar (SIHO, RIPS - Resolución 3374, habilitación, acreditación)?

## Bloque 8. Prueba piloto y siguientes pasos
46. Si se iniciara un piloto, ¿en qué servicio, con cuántas salas y durante qué periodo lo desarrollaríamos?
47. ¿Quién sería la contraparte técnica del hospital y qué disponibilidad semanal podría destinar?
48. ¿Qué resultado concreto tendría que evidenciar la aplicación para que ustedes la consideren útil?
49. ¿Sería posible acompañar y observar una jornada quirúrgica completa, sin intervenir en el proceso, para documentar el flujo real de información?
50. ¿Cuáles son los plazos e instancias internas de aprobación en caso de que decidan avanzar con la implementación?

## Respuestas
*(vacío — registrar aquí lo que respondió cada hospital)*
