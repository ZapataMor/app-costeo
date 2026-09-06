---
tags: [fuente, herramienta-digital, insumos, colombia]
origen: "FonnegraJuanita_2024_HerramientaDigitalCompras.pdf (39 págs.)"
procesado: 2026-07-11
---

# Fuente — Tesis "Herramienta digital para la gestión de compras de componentes biomédicos, Hospital Alma Máter" (U. de Antioquia, 2024)

**Autora:** Juanita Fonnegra Villegas (Bioingeniería). Asesor: Javier García. Universidad de Antioquia, Medellín, 2024. Caso: Hospital Alma Máter de Antioquia (HAMA, antes IPS Universitaria), servicio de Urgencias Adultos, sede León XIII.

> 🔑 Único documento del cerebro sobre **construcción de una herramienta digital en un hospital colombiano**. No trata de costeo: trata de la capa de **insumos/inventario/compras** — vecina del módulo de "captura de consumos" del aplicativo. Ojo: la "herramienta digital" es un **Excel con macros**, no una app web; su valor para nosotros está en los requisitos funcionales, no en la tecnología.

## Resumen

### Problema
El área de ingeniería biomédica compraba **ARIC** (accesorios, repuestos, insumos y consumibles) sin trazabilidad: para cada pedido había que rebuscar el número de parte en manuales o en el almacén, y no había forma automática de saber si un pedido estaba retrasado.

### Qué construyó (requisitos funcionales reutilizables)
1. **Base de datos de referencias** (1.400 referencias únicas: equipo, marca, modelo, número de parte, descripción) con buscador — al seleccionar una referencia se autocompleta la información del componente. *(= nuestra "búsqueda por código/nombre" en captura de consumos.)*
2. **Consolidado de pedidos** con: prioridad, estado, fechas de documentación/pedido/recepción, servicio solicitante, referencia, tipología, 3 proveedores potenciales, cantidad, valor unitario, valor total autocalculado y observaciones. Estado del pedido (a tiempo/retrasado) **calculado automáticamente** desde las fechas.
3. **Listas desplegables** para proveedores, servicios, tipología y prioridad → previenen errores de digitación. *(= catálogos maestros parametrizables por hospital.)*
4. **Dashboard dinámico**: compras por mes, por servicio y por proveedor, con escala temporal (día/mes/trimestre/año) y filtros.
5. Importó el **histórico 2021-2023** para validar la herramienta con datos reales y detectar patrones (ej. consumo inusual de un componente en un servicio puede indicar mal uso del equipo → capacitación, no sanción — mismo espíritu "no punitivo" del portal del cirujano de la tesis del profesor).
6. Entregables de adopción: instructivo PDF + video tutorial + prueba cronometrada de manejo (nº de clics y tiempo por compra).

### Marco normativo colombiano citado
**Resolución 3100/2019** (habilitación: dotación mínima de equipos por servicio — ya la teníamos por el libro del profesor), **Decreto 4725/2005** (registro sanitario de dispositivos médicos), Resolución 5039/1994.

## Aportes al cerebro
- Lista de campos y comportamientos probados en un hospital colombiano para el futuro **módulo de insumos/consumos** (catálogo maestro + autocompletado + estados automáticos + indicadores).
- Patrón de adopción replicable: importar histórico como validación + instructivo + video + medir clics/tiempo — útil para la fase de implantación en los 3 hospitales.
- Término nuevo al [[Glosario]]: **ARIC**.

## Citas útiles
> "La nueva herramienta cuenta con una base de datos integrada […] este proceso solo necesita realizarse una vez para ingresar la información a la base de datos, la cual se mantiene almacenada y disponible para futuros usos."

> "Si se observa un aumento repentino en las compras de un componente para un servicio en particular, esto podría indicar un uso inadecuado del equipo […] la información recopilada puede utilizarse para planificar programas de capacitación."
