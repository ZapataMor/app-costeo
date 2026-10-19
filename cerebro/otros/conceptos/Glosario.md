---
tags: [concepto, glosario]
migrado_de: "información-contexto/Cerebro/02 - Conceptos/Glosario.md"
---

# Glosario

Términos técnicos del dominio. Se amplía a medida que se procesan documentos.

| Término | Significado |
|---|---|
| **IPS** | Institución Prestadora de Servicios de salud (hospital, clínica, laboratorio). Quien atiende. |
| **EPS** | Entidad Promotora de Salud. Aseguradora que afilia y paga. |
| **ADRES** | Administradora de los Recursos del Sistema de Salud. El "banco" estatal del sistema. |
| **UPC** | Unidad de Pago por Capitación. Prima anual que recibe la EPS por cada afiliado. |
| **CUPS** | Clasificación Única de Procedimientos en Salud. Código estándar de cada procedimiento (ej. la apendicectomía tiene su código CUPS). |
| **CIE-10 / CIE-11** | Clasificación Internacional de Enfermedades. Códigos de diagnóstico. |
| **RIPS** | Registro Individual de Prestación de Servicios de salud. Reporte estándar de cada atención que la IPS envía al pagador/Ministerio. |
| **Glosa** | Objeción de la EPS a un cargo de la factura (no pertinente, mal soportado, tarifa errada). Dinero que el hospital deja de recibir mientras la resuelve. |
| **Radicación** | Presentación formal de la factura ante el pagador. |
| **Manual ISS 2001** | Tarifario de referencia histórico; se usa "ISS + %" en contratos. |
| **Manual SOAT** | Tarifario para víctimas de accidentes de tránsito. |
| **SOAT** | Seguro Obligatorio de Accidentes de Tránsito. |
| **ARL** | Administradora de Riesgos Laborales. Paga accidentes/enfermedades laborales. |
| **UCI** | Unidad de Cuidados Intensivos. Estancia de mayor costo por día. |
| **Remisión / Referencia** | Envío del paciente a una IPS de mayor complejidad. |
| **Egreso** | Salida del paciente (alta, remisión o fallecimiento); cierra la cuenta. |
| **Copago / Cuota moderadora** | Lo que el afiliado paga de su bolsillo según su nivel de ingresos. |
| **Estancia** | Días de hospitalización; se factura por día según el tipo de cama. |
| **Costeo ABC** | Costeo Basado en Actividades; método contable frecuente en salud para saber cuánto cuesta *realmente* un servicio. Ver [[Costeo ABC y TDABC]]. |
| **TDABC** | Time-Driven ABC: costeo por costo/minuto de cada recurso × tiempo de uso. Usado para cirugías de alto volumen. |
| **E.S.E.** | Empresa Social del Estado: hospital público con autonomía que debe autosostenerse vendiendo servicios (Ley 100/1993). |
| **Inductor (cost driver)** | Variable que distribuye costos con relación causa-efecto (m², kWh, tiempo, nº de egresos). Hay de 1er, 2º y 3er nivel. |
| **Centro de costos** | Unidad mínima de gestión donde se acumulan costos (ej. hospitalización, cirugía, urgencias). |
| **Objeto de costo** | Lo que se quiere costear: día de estancia, egreso, procedimiento, paciente, patología o GRD. |
| **GRD** | Grupo Relacionado de Diagnóstico: agrupación de pacientes clínicamente similares con consumo de recursos similar. |
| **Día de estancia** | Unidad estándar del producto hospitalario: un día de un paciente hospitalizado con todo lo que consume. |
| **SGC** | Sistema de Gestión del Conocimiento. Ver [[Sistema de Gestion del Conocimiento (SGC)]]. |
| **CMM-GC** | Modelo de madurez (1-5) en gestión del conocimiento; los hospitales de La Guajira están en Nivel 3. |
| **TOGAF ADM** | Metodología de arquitectura empresarial (fases A-H) usada en la tesis para diseñar el SGC. |
| **MOD** | Mano de Obra Directa (equipo quirúrgico: cirujano, anestesiólogo, instrumentadora...). |
| **Supersalud** | Superintendencia Nacional de Salud; vigila y puede intervenir instituciones (como al HSJ de Maicao en 2016). |
| **Capacidad práctica** | Tiempo realmente disponible de un recurso para trabajar: ~80% de la jornada teórica en personas, ~85% en máquinas. Es el denominador del costo/minuto en TDABC. |
| **Tasa del costo de capacidad** | Costo total de un grupo de recursos ÷ capacidad práctica en minutos = el "costo por minuto" del TDABC. |
| **Ecuación de tiempo** | Forma TDABC de modelar variantes de un proceso: tiempo base + términos aditivos condicionales (ej. + X min si hay complicación), en vez de crear actividades nuevas. |
| **Capacidad no utilizada** | Diferencia entre la capacidad práctica y la usada realmente; su costo no se asigna a ningún servicio y revela subutilización (ej. quirófanos ociosos). |
| **Punto de equilibrio** | Nº mínimo de cirugías/mes (según la mezcla de procedimientos) cuyo margen de contribución cubre los costos fijos; por debajo, el hospital pierde. |
| **Margen de contribución** | Precio de venta − costo variable unitario; lo que cada cirugía aporta para cubrir costos fijos. |
| **ARIC** | Accesorios, Repuestos, Insumos y Consumibles de equipos biomédicos (término del caso HAMA de Medellín). |
| **CIF** | Costos Indirectos de Fabricación (en salud: todo costo del servicio que no es MOD ni insumo directo). En los casos estudiados ronda el 25-40% del costo total. |
| **MILP** | *Mixed-Integer Linear Programming*, programación lineal entera mixta. En la tesis, modelo que reasigna cirugías a quirófanos y días para minimizar apertura de salas, horas extra y cancelaciones. Tercer objetivo doctoral, fuera del alcance del aplicativo por ahora. |
| **Clasificación ASA** | Escala de la American Society of Anesthesiologists (I-VI) del estado físico del paciente antes de la anestesia; predictor de duración y complicaciones. Campo propuesto en la *Fundamentación*, no capturado aún por el aplicativo. |
| **Turnover time** | Tiempo entre la salida de un paciente del quirófano y el inicio de la siguiente cirugía en la misma sala (alistamiento + aseo). Línea base típica 40-60 min; meta 25-35. |
| **Prioridad quirúrgica** | Electiva / urgente / emergente. El aplicativo modela hoy solo programada / urgencia. |
| **Costo estándar (TDABC)** | Costo de una cirugía con su duración **planificada** y sin factor de ineficiencia: la referencia contra la que se mide la desviación del costo real. |
| **Factor de ineficiencia institucional** | Multiplicador del costo de capacidad (no de materiales) que representa la ineficiencia de cada hospital según su madurez: 1,28 HSJ · 1,20 HNR · 1,15 HSR en el pipeline doctoral. |
