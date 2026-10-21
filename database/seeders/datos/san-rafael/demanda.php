<?php

/**
 * Demanda quirúrgica mensual del Hospital San Rafael por procedimiento.
 *
 * ~330 cirugías al mes en 5 salas (ocupación cercana al 35 % de la capacidad
 * de 12 h × 26 días, habitual en la red pública de La Guajira [VERIFICAR]).
 * La obstetricia domina, como en toda la región; los procedimientos menores
 * y la ortopedia de trauma vienen después.
 *
 * Columnas: CUPS · casos al mes · sala habitual · diagnóstico CIE-10 ·
 * perfil del paciente · proporción de urgencias.
 */
return [
    // ── Ginecobstetricia ──────────────────────────────────────────────────
    ['740001', 45, 'Sala 3 - Obstétrica', 'O82', 'gestante', 0.65],
    ['741000', 18, 'Sala 3 - Obstétrica', 'O82', 'gestante', 0.40],
    ['690200', 22, 'Sala 3 - Obstétrica', 'O03.4', 'gestante', 0.80],
    ['695100', 12, 'Sala 5 - Procedimientos menores', 'O03.4', 'gestante', 0.50],
    ['663200', 10, 'Sala 3 - Obstétrica', 'Z30.2', 'mujer', 0.0],
    ['684900', 6, 'Sala 3 - Obstétrica', 'D25.9', 'mujer_mayor', 0.0],
    ['685900', 3, 'Sala 3 - Obstétrica', 'N81.2', 'mujer_mayor', 0.0],
    ['666200', 4, 'Sala 3 - Obstétrica', 'O00.1', 'gestante', 1.0],
    ['652900', 4, 'Sala 3 - Obstétrica', 'N83.2', 'mujer', 0.25],
    ['675100', 2, 'Sala 3 - Obstétrica', 'O34.3', 'gestante', 0.20],
    ['705000', 3, 'Sala 3 - Obstétrica', 'N81.1', 'mujer_mayor', 0.0],
    ['672000', 3, 'Sala 5 - Procedimientos menores', 'N87.1', 'mujer', 0.0],
    ['756200', 3, 'Sala 3 - Obstétrica', 'O70.2', 'gestante', 1.0],

    // ── Cirugía general ───────────────────────────────────────────────────
    ['780201', 22, 'Sala 1', 'K35.8', 'joven', 1.0],
    ['470100', 6, 'Sala 2', 'K35.8', 'joven', 0.90],
    ['741500', 14, 'Sala 2', 'K80.2', 'adulto', 0.15],
    ['512200', 6, 'Sala 1', 'K80.2', 'adulto', 0.40],
    ['744201', 10, 'Sala 1', 'K40.9', 'hombre', 0.10],
    ['531000', 8, 'Sala 1', 'K42.9', 'adulto', 0.10],
    ['535100', 3, 'Sala 1', 'K43.9', 'adulto', 0.10],
    ['541100', 5, 'Sala 1', 'R10.0', 'adulto', 0.90],
    ['494600', 4, 'Sala 5 - Procedimientos menores', 'K64.9', 'adulto', 0.0],
    ['491200', 2, 'Sala 5 - Procedimientos menores', 'K60.3', 'adulto', 0.0],
    ['860400', 12, 'Sala 5 - Procedimientos menores', 'L02.9', 'adulto', 0.70],
    ['862200', 10, 'Sala 5 - Procedimientos menores', 'L08.9', 'adulto', 0.50],
    ['863000', 5, 'Sala 5 - Procedimientos menores', 'D17.9', 'adulto', 0.0],
    ['063900', 1, 'Sala 2', 'E04.1', 'mujer', 0.0],

    // ── Ortopedia y traumatología ─────────────────────────────────────────
    ['793500', 4, 'Sala 4 - Ortopedia', 'S72.3', 'trauma', 0.60],
    ['793600', 4, 'Sala 4 - Ortopedia', 'S82.2', 'trauma', 0.50],
    ['793200', 5, 'Sala 4 - Ortopedia', 'S52.5', 'trauma', 0.30],
    ['793800', 4, 'Sala 4 - Ortopedia', 'S82.8', 'trauma', 0.30],
    ['786000', 4, 'Sala 4 - Ortopedia', 'Z47.0', 'trauma', 0.0],
    ['806000', 2, 'Sala 4 - Ortopedia', 'S83.2', 'hombre', 0.0],
    ['841100', 3, 'Sala 5 - Procedimientos menores', 'S68.1', 'trauma', 0.90],
    ['841500', 2, 'Sala 4 - Ortopedia', 'E11.5', 'adulto_mayor', 0.30],
    ['824400', 3, 'Sala 4 - Ortopedia', 'S66.0', 'trauma', 0.60],
    ['790100', 12, 'Sala 4 - Ortopedia', 'S52.5', 'trauma', 0.80],
    ['796000', 6, 'Sala 4 - Ortopedia', 'S82.2', 'trauma', 1.0],

    // ── Urología ──────────────────────────────────────────────────────────
    ['602900', 3, 'Sala 2', 'N40', 'hombre_mayor', 0.0],
    ['603000', 1, 'Sala 2', 'N40', 'hombre_mayor', 0.0],
    ['640000', 4, 'Sala 5 - Procedimientos menores', 'N47', 'nino_varon', 0.0],
    ['612000', 2, 'Sala 5 - Procedimientos menores', 'N43.3', 'hombre', 0.0],
    ['625000', 1, 'Sala 2', 'Q53.1', 'nino_varon', 0.0],
    ['573200', 3, 'Sala 2', 'R31', 'adulto_mayor', 0.0],
    ['637300', 3, 'Sala 5 - Procedimientos menores', 'Z30.2', 'hombre', 0.0],

    // ── Otorrino, plástica, pediátrica, oftalmología ──────────────────────
    ['283000', 4, 'Sala 1', 'J35.0', 'nino', 0.0],
    ['286000', 2, 'Sala 1', 'J35.2', 'nino', 0.0],
    ['215000', 1, 'Sala 1', 'J34.2', 'adulto', 0.0],
    ['866000', 2, 'Sala 1', 'T21.2', 'trauma', 0.30],
    ['530300', 3, 'Sala 1', 'K40.9', 'nino', 0.0],
    ['134100', 6, 'Sala 5 - Procedimientos menores', 'H25.9', 'anciano', 0.0],
    ['113900', 3, 'Sala 5 - Procedimientos menores', 'H11.0', 'adulto', 0.0],
];
