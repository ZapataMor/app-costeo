---
tags: [fuente, instrumento]
origen: "TABLAS RECOLECCION DE INFORMACION DE COSTOS PROCESOS QUIRURGICOS.xlsx"
procesado: 2026-07-05
---

# Fuente — Tablas de Recolección de Costos de Procesos Quirúrgicos (Excel)

Instrumento de **recolección de datos en campo** de la tesis. Es una plantilla vacía con **10 hojas idénticas ("PROCED No 1" a "PROCED No 10")** — una por procedimiento quirúrgico a costear.

## Estructura de cada hoja (= el modelo de datos del costo de una cirugía)
1. **Mano de obra directa (MOD)**: cirujano general, cirujano ayudante, anestesiólogo, instrumentadora, enfermera circulante, auxiliar de enfermería — con salario mensual + prestaciones, **tiempo en minutos/horas** y costo MOD calculado. *(esto es TDABC puro)*
2. **Medicamentos**: propofol, fentanilo, rocuronio, sevoflurano, dipirona, tramadol, cefazolina, soluciones... con presentación, cantidad y costo unitario.
3. **Material quirúrgico / insumos**: guantes, bisturí, suturas (Vicryl, Nylon), gasas, catéteres, tubo endotraqueal, sonda Foley, jeringas...
4. **Esterilización**: papel crepado, cinta testigo, indicador químico.
5. **Ropa quirúrgica desechable**: bata, campos, gorro, tapabocas.
6. **Costos del quirófano por minuto**: depreciación del inmueble, servicios públicos, mantenimiento, aseo, seguros → `minutos disponibles/mes = 12 h/día × 26 días`.
7. **Depreciación de equipos**: mesa quirúrgica, máquina de anestesia, monitor, electrobisturí, lámpara cielítica, aspirador — con vida útil y minutos de uso.
8. **Personal indirecto**: jefe de enfermería, coordinador quirúrgico, camillero, esterilización, aseo — prorrateado por cirugías/mes.
9. **Costos logísticos**: lavandería, residuos hospitalarios.

## Por qué importa para el aplicativo
Esta plantilla **es el borrador del modelo de entidades** del sistema de costeo quirúrgico ([[Costeo ABC y TDABC]]): recursos con costo por unidad de tiempo + tiempos medidos + consumos = costo real del procedimiento. Un formulario web que reemplace este Excel es probablemente el primer módulo a construir.
