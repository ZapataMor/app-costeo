<?php

/**
 * Equipos biomédicos que se añaden al Hospital San Rafael.
 *
 * Una fila por unidad física, ubicada en su sala. Con los tres [SEMILLA]
 * (EQ-001 electrobisturí y EQ-003 monitor de la Sala 1, EQ-002 torre de
 * laparoscopia de la Sala 2) cada quirófano queda con su máquina de
 * anestesia, monitor y electrobisturí; los equipos de especialidad son
 * únicos y la recuperación (URPA) tiene cuatro cubículos monitorizados.
 *
 * Columnas: código · nombre · valor de adquisición · vida útil (años) ·
 * horas de uso al año.
 *
 * El seeder deriva el costo por hora:
 *   costo_hora = (valor ÷ vida útil + 8 % del valor en mantenimiento) ÷ horas de uso/año
 * Los equipos de especialidad se usan pocas horas al año, así que su hora
 * resulta cara: es lo que cuesta tenerlos disponibles.
 *
 * Valores de referencia 2026 en COP [VERIFICAR con el inventario de activos].
 */
return [
    // ── Máquinas de anestesia (una por quirófano mayor) ───────────────────
    ['EQ-ANE-01', 'Máquina de anestesia Dräger Fabius Plus - Sala 1', 185_000_000, 10, 1_800],
    ['EQ-ANE-02', 'Máquina de anestesia Dräger Fabius Plus - Sala 2', 185_000_000, 10, 1_800],
    ['EQ-ANE-03', 'Máquina de anestesia Mindray WATO EX-35 - Sala 3', 160_000_000, 10, 1_800],
    ['EQ-ANE-04', 'Máquina de anestesia Mindray WATO EX-55 - Sala 4', 195_000_000, 10, 1_800],

    // ── Monitores multiparámetro ──────────────────────────────────────────
    ['EQ-MON-02', 'Monitor multiparámetro Mindray ePM 12 - Sala 2', 30_000_000, 7, 2_000],
    ['EQ-MON-03', 'Monitor multiparámetro Mindray ePM 12 - Sala 3', 30_000_000, 7, 2_000],
    ['EQ-MON-04', 'Monitor multiparámetro Mindray ePM 12 - Sala 4', 30_000_000, 7, 2_000],
    ['EQ-MON-05', 'Monitor multiparámetro Mindray iMEC 10 - Sala 5', 18_000_000, 7, 2_000],
    ['EQ-MON-R1', 'Monitor de signos vitales - URPA cubículo 1', 15_000_000, 7, 2_500],
    ['EQ-MON-R2', 'Monitor de signos vitales - URPA cubículo 2', 15_000_000, 7, 2_500],
    ['EQ-MON-R3', 'Monitor de signos vitales - URPA cubículo 3', 15_000_000, 7, 2_500],
    ['EQ-MON-R4', 'Monitor de signos vitales - URPA cubículo 4', 15_000_000, 7, 2_500],

    // ── Electrobisturíes ──────────────────────────────────────────────────
    ['EQ-ELB-02', 'Electrobisturí Valleylab Force FX - Sala 2', 45_000_000, 8, 1_600],
    ['EQ-ELB-03', 'Electrobisturí Valleylab Force FX - Sala 3', 45_000_000, 8, 1_600],
    ['EQ-ELB-04', 'Electrobisturí Valleylab Force FX - Sala 4', 45_000_000, 8, 1_600],
    ['EQ-ELB-05', 'Electrobisturí monopolar compacto - Sala 5', 22_000_000, 8, 1_200],

    // ── Cirugía general, laparoscopia y urología ──────────────────────────
    ['EQ-LAP-02', 'Torre de laparoscopia Storz HD (respaldo) - Sala 1', 260_000_000, 10, 600],
    ['EQ-SEL-01', 'Sellador de vasos (LigaSure) - Sala 2', 150_000_000, 8, 700],
    ['EQ-URO-01', 'Torre de endourología con resectoscopio y cistoscopio - Sala 2', 160_000_000, 10, 500],

    // ── Ortopedia (Sala 4) ────────────────────────────────────────────────
    ['EQ-ARC-01', 'Arco en C (fluoroscopio) Siemens Cios Select - Sala 4', 420_000_000, 10, 500],
    ['EQ-MOR-01', 'Mesa ortopédica con tracción - Sala 4', 95_000_000, 15, 500],
    ['EQ-MOT-01', 'Motor quirúrgico de ortopedia (taladro y sierra) No. 1', 65_000_000, 8, 400],
    ['EQ-MOT-02', 'Motor quirúrgico de ortopedia (taladro y sierra) No. 2', 65_000_000, 8, 400],
    ['EQ-TOR-01', 'Torniquete neumático digital No. 1', 28_000_000, 8, 500],
    ['EQ-TOR-02', 'Torniquete neumático digital No. 2', 28_000_000, 8, 500],
    ['EQ-ART-01', 'Torre de artroscopia con shaver - Sala 4', 320_000_000, 10, 600],
    ['EQ-ECO-01', 'Ecógrafo portátil para bloqueos regionales', 140_000_000, 8, 800],

    // ── Oftalmología (Sala 5) ─────────────────────────────────────────────
    ['EQ-MIC-01', 'Microscopio quirúrgico oftalmológico - Sala 5', 280_000_000, 12, 600],
    ['EQ-FAC-01', 'Facoemulsificador - Sala 5', 240_000_000, 10, 600],

    // ── Obstetricia y neonatos ────────────────────────────────────────────
    ['EQ-CUN-01', 'Cuna de calor radiante - Sala 3', 48_000_000, 10, 1_500],
    ['EQ-CUN-02', 'Cuna de calor radiante - Sala de partos', 48_000_000, 10, 1_500],
    ['EQ-DOP-01', 'Doppler fetal portátil - Sala 3', 3_200_000, 5, 1_000],
    ['EQ-DOP-02', 'Doppler fetal portátil - Sala de partos', 3_200_000, 5, 1_000],
    ['EQ-MFE-01', 'Monitor fetal (cardiotocógrafo) - Central obstétrica', 38_000_000, 8, 1_500],

    // ── Soporte compartido de la central quirúrgica ───────────────────────
    ['EQ-DEF-01', 'Desfibrilador bifásico - Central quirúrgica', 32_000_000, 8, 1_000],
    ['EQ-DEF-02', 'Desfibrilador bifásico - URPA', 32_000_000, 8, 1_000],
    ['EQ-VLA-01', 'Videolaringoscopio No. 1', 18_000_000, 6, 800],
    ['EQ-VLA-02', 'Videolaringoscopio No. 2', 18_000_000, 6, 800],
    ['EQ-CAL-01', 'Calentador de pacientes por aire forzado - Sala 1', 22_000_000, 8, 1_500],
    ['EQ-CAL-02', 'Calentador de pacientes por aire forzado - Sala 2', 22_000_000, 8, 1_500],
    ['EQ-CAL-03', 'Calentador de pacientes por aire forzado - Sala 4', 22_000_000, 8, 1_500],
    ['EQ-CFL-01', 'Calentador de fluidos No. 1', 12_000_000, 7, 1_200],
    ['EQ-CFL-02', 'Calentador de fluidos No. 2', 12_000_000, 7, 1_200],
    ['EQ-ASP-01', 'Aspirador quirúrgico portátil - Sala 1', 9_000_000, 8, 1_800],
    ['EQ-ASP-02', 'Aspirador quirúrgico portátil - Sala 2', 9_000_000, 8, 1_800],
    ['EQ-ASP-03', 'Aspirador quirúrgico portátil - Sala 3', 9_000_000, 8, 1_800],
    ['EQ-ASP-04', 'Aspirador quirúrgico portátil - Sala 4', 9_000_000, 8, 1_800],
    ['EQ-ASP-05', 'Aspirador quirúrgico portátil - Sala 5', 9_000_000, 8, 1_800],
    ['EQ-BIN-01', 'Bomba de infusión volumétrica No. 1', 8_500_000, 7, 2_500],
    ['EQ-BIN-02', 'Bomba de infusión volumétrica No. 2', 8_500_000, 7, 2_500],
    ['EQ-BIN-03', 'Bomba de infusión volumétrica No. 3', 8_500_000, 7, 2_500],
    ['EQ-BIN-04', 'Bomba de infusión volumétrica No. 4', 8_500_000, 7, 2_500],
    ['EQ-BIN-05', 'Bomba de infusión volumétrica No. 5', 8_500_000, 7, 2_500],
    ['EQ-BIN-06', 'Bomba de infusión volumétrica No. 6', 8_500_000, 7, 2_500],
    ['EQ-BIN-07', 'Bomba de infusión volumétrica No. 7', 8_500_000, 7, 2_500],
    ['EQ-BIN-08', 'Bomba de infusión volumétrica No. 8', 8_500_000, 7, 2_500],
    ['EQ-VEN-01', 'Ventilador de transporte', 65_000_000, 8, 600],
    ['EQ-ECG-01', 'Electrocardiógrafo de 12 derivaciones', 14_000_000, 8, 800],
];
