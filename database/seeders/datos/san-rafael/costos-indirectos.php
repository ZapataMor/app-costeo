<?php

/**
 * Bolsas de costo indirecto (CIF) del servicio de cirugía del Hospital San
 * Rafael, vigentes desde el 2026-01-01.
 *
 * Montos mensuales imputables al bloque quirúrgico (5 salas), no al hospital
 * entero [VERIFICAR con la contabilidad del hospital: ver preguntas abiertas
 * del CIF].
 *
 * Las dos bolsas de equipos no llevan monto aquí: el seeder las calcula del
 * inventario sembrado (depreciación lineal y 8 % anual de mantenimiento),
 * para que sean coherentes con el costo/hora de cada equipo.
 *
 * Todas nacen INACTIVAS: activarlas es una decisión del hospital y solo se
 * hace desde ActivarCategoriaCif (pantalla de costos indirectos), que a la
 * vez marca el componente directo equivalente para no contarlo dos veces.
 *
 * Columnas: nombre · categoría · base de asignación · monto mensual o
 * porcentaje (null = calculado por el seeder).
 */
return [
    // Infraestructura — reemplaza el costo/hora de las salas.
    ['Servicios públicos del bloque quirúrgico (energía, agua, gases)', 'infraestructura', 'minuto_quirofano', 18_500_000],
    ['Mantenimiento de infraestructura y aire acondicionado de quirófanos', 'infraestructura', 'minuto_quirofano', 9_800_000],
    ['Aseo y desinfección terminal de quirófanos', 'infraestructura', 'minuto_quirofano', 7_200_000],
    ['Vigilancia y seguros del bloque quirúrgico', 'infraestructura', 'minuto_quirofano', 3_600_000],

    // Equipos — reemplaza el costo/hora de los equipos médicos.
    ['Depreciación de equipos biomédicos de cirugía', 'depreciacion_equipos', 'minuto_quirofano', null],
    ['Mantenimiento preventivo y correctivo biomédico', 'depreciacion_equipos', 'minuto_quirofano', null],

    // Personal indirecto — reemplaza los costos indirectos mensuales del personal.
    ['Coordinación médica y jefatura de enfermería de cirugía', 'personal_indirecto', 'minuto_personal', 14_500_000],
    ['Camilleros y auxiliares de apoyo de cirugía', 'personal_indirecto', 'minuto_personal', 9_600_000],

    // Sin equivalente en el costo directo.
    ['Gastos de administración (gerencia, facturación, sistemas, talento humano)', 'administracion', 'porcentaje_directo', 0.085],
    ['Central de esterilización', 'servicios_generales', 'minuto_quirofano', 16_800_000],
    ['Lavandería y ropería quirúrgica', 'servicios_generales', 'minuto_quirofano', 6_900_000],
    ['Gestión de residuos hospitalarios', 'servicios_generales', 'minuto_quirofano', 3_100_000],
];
