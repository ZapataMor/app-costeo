<?php

/**
 * Procedimientos que se añaden al Hospital San Rafael, con su protocolo.
 *
 * Portafolio quirúrgico de un hospital de mediana complejidad: cirugía
 * general, ginecobstetricia, ortopedia, urología, otorrino, plástica,
 * cirugía pediátrica y oftalmología.
 *
 * Códigos CUPS: construidos sobre la estructura CIE-9-MC de la que derivan
 * los CUPS, no copiados de la resolución vigente [VERIFICAR contra el
 * listado oficial antes de usarlos fuera de pruebas]. Tarifas SOAT −25 %
 * aproximadas a 2026.
 *
 * `tiempos` = [sala, prequirúrgico, recuperación, recambio] en minutos.
 *
 * El protocolo se arma con kits (tipo de anestesia + paquete de cirugía
 * mayor o menor + analgesia posoperatoria) más los insumos propios del
 * procedimiento. `pre` y `post` son fases del ciclo; el resto es quirúrgica.
 * Los equipos son unidades concretas: las de la sala donde se hace.
 *
 * @var array<string, array<string, array<string, int|float>>> $kits
 */
$kits = [
    'general' => ['quirurgica' => [
        'MED-010' => 1, 'MED-015' => 1, 'MED-024' => 1, 'MED-013' => 0.15, 'MED-014' => 1,
        'MED-037' => 1, 'MED-035' => 1, 'MED-027' => 1, 'MED-029' => 1, 'MED-063' => 2,
        'MED-066' => 0.5, 'DIS-013' => 1, 'DIS-016' => 1, 'DIS-017' => 1, 'DIS-015' => 1,
        'DIS-004' => 1, 'MAT-040' => 1, 'MAT-045' => 5, 'MAT-035' => 4,
    ]],
    'raquidea' => ['quirurgica' => [
        'MED-007' => 1, 'DIS-022' => 1, 'MED-015' => 1, 'MED-030' => 1, 'MED-004' => 1,
        'MED-063' => 2, 'MED-037' => 1, 'MED-066' => 0.3, 'DIS-004' => 1, 'DIS-018' => 1,
        'MAT-040' => 1, 'MAT-045' => 3, 'MAT-034' => 2,
    ]],
    // Aguja punta de lápiz: menos cefalea pospunción en la gestante.
    'raquidea_obstetrica' => ['quirurgica' => [
        'MED-007' => 1, 'DIS-023' => 1, 'MED-015' => 1, 'MED-030' => 1, 'MED-004' => 1,
        'MED-063' => 2, 'MED-037' => 1, 'MED-066' => 0.3, 'DIS-004' => 1, 'DIS-018' => 1,
        'MAT-040' => 1, 'MAT-045' => 3, 'MAT-034' => 2,
    ]],
    'sedacion' => ['quirurgica' => [
        'MED-014' => 1, 'MED-015' => 1, 'MED-010' => 1, 'MED-062' => 1, 'MED-066' => 0.2,
        'DIS-018' => 1, 'DIS-005' => 1, 'MAT-040' => 1, 'MAT-045' => 3, 'MAT-035' => 2,
    ]],
    'local' => ['quirurgica' => [
        'MED-005' => 1, 'MAT-035' => 1, 'MAT-038' => 2,
    ]],
    'mayor' => [
        'pre' => ['MED-003' => 1],
        'quirurgica' => [
            'MAT-003' => 6, 'MAT-023' => 4, 'MAT-024' => 5, 'MAT-025' => 5, 'MAT-027' => 4,
            'MAT-004' => 1, 'MAT-033' => 1, 'MAT-002' => 10, 'MAT-006' => 5, 'MAT-020' => 1,
            'DIS-020' => 1, 'DIS-021' => 1, 'MAT-056' => 1, 'MED-077' => 1, 'MED-076' => 1,
            'MAT-049' => 1, 'MAT-064' => 2,
        ],
    ],
    'menor' => ['quirurgica' => [
        'MAT-003' => 3, 'MAT-023' => 2, 'MAT-024' => 3, 'MAT-025' => 3, 'MAT-004' => 1,
        'MAT-006' => 3, 'MAT-021' => 1, 'MED-076' => 1, 'MAT-048' => 1, 'MAT-064' => 1,
    ]],
    'post_mayor' => ['post' => [
        'MED-020' => 3, 'MED-019' => 2, 'MED-037' => 2, 'MED-062' => 2, 'MED-039' => 1,
    ]],
    'post_menor' => ['post' => ['MED-020' => 1]],
];

// Equipos fijos de cada sala (los [SEMILLA] EQ-001 y EQ-003 son de la Sala 1).
$sala1 = ['EQ-ANE-01', 'EQ-003', 'EQ-001', 'EQ-ASP-01'];
$sala2 = ['EQ-ANE-02', 'EQ-MON-02', 'EQ-ELB-02', 'EQ-ASP-02'];
$sala3 = ['EQ-ANE-03', 'EQ-MON-03', 'EQ-ELB-03', 'EQ-ASP-03'];
$sala4 = ['EQ-ANE-04', 'EQ-MON-04', 'EQ-ELB-04', 'EQ-ASP-04'];
$sala5 = ['EQ-MON-05', 'EQ-ELB-05', 'EQ-ASP-05'];

// Equipos humanos tipo.
$completo = ['cirujano' => 1, 'ayudante' => 1, 'anestesiologo' => 1, 'instrumentador' => 1, 'circulante' => 1];
$sinAyudante = ['cirujano' => 1, 'anestesiologo' => 1, 'instrumentador' => 1, 'circulante' => 1];
$local = ['cirujano' => 1, 'instrumentador' => 1, 'circulante' => 1];

return [
    'kits' => $kits,

    // Los cuatro procedimientos [SEMILLA] nacieron con un protocolo mínimo
    // (sin anestesia ni analgesia). Se completan sin tocar sus líneas: solo
    // se añaden las que faltan, para que su costo sea comparable con los
    // nuevos. La cesárea de referencia de $520.000 no usa la plantilla.
    'complementos' => [
        '740001' => [
            'kits' => ['raquidea_obstetrica', 'post_mayor'],
            'insumos' => ['MED-052' => 2, 'MED-067' => 1, 'MAT-011' => 2, 'DIS-007' => 1, 'DIS-009' => 1, 'DIS-050' => 1],
            'equipos' => [...$sala3, 'EQ-CUN-01'],
        ],
        '780201' => [
            'kits' => ['general', 'post_mayor'],
            'pre' => ['MED-042' => 1],
            'equipos' => ['EQ-ANE-01', 'EQ-ASP-01'],
        ],
        '741500' => [
            'kits' => ['general', 'post_mayor'],
            'insumos' => ['DIS-028' => 1, 'DIS-029' => 2, 'DIS-031' => 1, 'MAT-066' => 1],
            'equipos' => ['EQ-ANE-02', 'EQ-ASP-02'],
        ],
        '744201' => [
            'kits' => ['raquidea', 'post_menor'],
            'equipos' => ['EQ-ANE-01', 'EQ-ASP-01'],
        ],
    ],

    'procedimientos' => [
        // ── Cirugía general ───────────────────────────────────────────────
        [
            'cups' => '470100', 'nombre' => 'Apendicectomía por laparoscopia', 'especialidad' => 'Cirugía general',
            'complejidad' => 'media', 'tiempos' => [75, 40, 120, 30], 'tarifa' => 1_750_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'pre' => ['MED-042' => 1],
            'insumos' => ['DIS-028' => 1, 'DIS-029' => 2, 'DIS-031' => 1, 'DIS-030' => 1, 'MAT-066' => 1, 'MAT-008' => 1, 'MAT-013' => 1],
            'equipos' => [...$sala2, 'EQ-002', 'EQ-CAL-02'], 'personal' => $completo,
        ],
        [
            'cups' => '512200', 'nombre' => 'Colecistectomía abierta', 'especialidad' => 'Cirugía general',
            'complejidad' => 'media', 'tiempos' => [100, 40, 180, 30], 'tarifa' => 1_450_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-007' => 2, 'MAT-008' => 2, 'MAT-017' => 1, 'MAT-013' => 1, 'MAT-055' => 1, 'DIS-025' => 1, 'DIS-010' => 1],
            'equipos' => $sala1, 'personal' => $completo,
        ],
        [
            'cups' => '531000', 'nombre' => 'Herniorrafia umbilical con malla', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [60, 30, 90, 20], 'tarifa' => 680_000,
            'kits' => ['raquidea', 'mayor', 'post_menor'],
            'insumos' => ['DIS-055' => 1, 'MAT-016' => 1, 'MAT-008' => 1, 'MAT-013' => 1],
            'equipos' => $sala1, 'personal' => $completo,
        ],
        [
            'cups' => '535100', 'nombre' => 'Eventrorrafia con malla', 'especialidad' => 'Cirugía general',
            'complejidad' => 'media', 'tiempos' => [100, 40, 150, 25], 'tarifa' => 1_350_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['DIS-002' => 1, 'MAT-015' => 2, 'MAT-008' => 2, 'DIS-027' => 1, 'DIS-026' => 1, 'MAT-055' => 1],
            'equipos' => $sala1, 'personal' => $completo,
        ],
        [
            'cups' => '541100', 'nombre' => 'Laparotomía exploratoria', 'especialidad' => 'Cirugía general',
            'complejidad' => 'alta', 'tiempos' => [150, 45, 240, 30], 'tarifa' => 2_100_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'pre' => ['MED-042' => 1, 'MED-041' => 1],
            'insumos' => ['MAT-055' => 2, 'MAT-018' => 1, 'MAT-008' => 2, 'MAT-017' => 2, 'MAT-058' => 3, 'DIS-027' => 1, 'DIS-010' => 1, 'DIS-007' => 1, 'DIS-009' => 1, 'DIS-026' => 1],
            'equipos' => [...$sala1, 'EQ-CAL-01'], 'personal' => $completo,
        ],
        [
            'cups' => '494600', 'nombre' => 'Hemorroidectomía', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [45, 30, 90, 20], 'tarifa' => 720_000,
            'kits' => ['raquidea', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 2, 'MED-005' => 1, 'DIS-032' => 1],
            'equipos' => $sala5, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '491200', 'nombre' => 'Fistulectomía anal', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [45, 30, 90, 20], 'tarifa' => 690_000,
            'kits' => ['raquidea', 'menor', 'post_menor'],
            'pre' => ['MED-042' => 1],
            'insumos' => ['MAT-012' => 1],
            'equipos' => $sala5, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '860400', 'nombre' => 'Drenaje de absceso de piel y tejido subcutáneo', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [30, 20, 45, 15], 'tarifa' => 320_000,
            'kits' => ['local', 'menor', 'post_menor'],
            'insumos' => ['DIS-025' => 1, 'MAT-058' => 1],
            'equipos' => [], 'personal' => $local,
        ],
        [
            'cups' => '862200', 'nombre' => 'Desbridamiento quirúrgico de herida', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [40, 20, 60, 15], 'tarifa' => 410_000,
            'kits' => ['sedacion', 'menor', 'post_menor'],
            'insumos' => ['MAT-058' => 2, 'MAT-055' => 1, 'MAT-051' => 1],
            'equipos' => $sala5, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '863000', 'nombre' => 'Resección de lipoma', 'especialidad' => 'Cirugía general',
            'complejidad' => 'baja', 'tiempos' => [35, 20, 45, 15], 'tarifa' => 380_000,
            'kits' => ['local', 'menor', 'post_menor'],
            'insumos' => ['MAT-009' => 1, 'MAT-014' => 1],
            'equipos' => ['EQ-ELB-05'], 'personal' => $local,
        ],
        [
            'cups' => '063900', 'nombre' => 'Hemitiroidectomía', 'especialidad' => 'Cirugía general',
            'complejidad' => 'alta', 'tiempos' => [150, 45, 180, 30], 'tarifa' => 2_350_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-009' => 3, 'MAT-010' => 1, 'MAT-017' => 2, 'DIS-026' => 1, 'DIS-032' => 1],
            'equipos' => [...$sala2, 'EQ-SEL-01'], 'personal' => $completo,
        ],

        // ── Ginecobstetricia ──────────────────────────────────────────────
        [
            'cups' => '741000', 'nombre' => 'Cesárea segmentaria con pomeroy', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media', 'tiempos' => [75, 30, 120, 20], 'tarifa' => 980_000,
            'kits' => ['raquidea_obstetrica', 'mayor', 'post_mayor'],
            'insumos' => ['MED-001' => 4, 'MED-052' => 2, 'MED-067' => 1, 'MAT-011' => 3, 'MAT-012' => 2, 'MAT-009' => 1, 'MAT-032' => 1, 'DIS-007' => 1, 'DIS-009' => 1, 'DIS-050' => 1],
            'equipos' => [...$sala3, 'EQ-CUN-01'],
            // La segunda circulante recibe al recién nacido.
            'personal' => [...$completo, 'circulante' => 2],
        ],
        [
            'cups' => '684900', 'nombre' => 'Histerectomía abdominal total', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'alta', 'tiempos' => [140, 45, 240, 30], 'tarifa' => 2_600_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-007' => 4, 'MAT-011' => 3, 'MAT-008' => 2, 'MAT-055' => 2, 'MED-052' => 2, 'DIS-007' => 1, 'DIS-009' => 1, 'DIS-027' => 1],
            'equipos' => $sala3, 'personal' => $completo,
        ],
        [
            'cups' => '685900', 'nombre' => 'Histerectomía vaginal', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'alta', 'tiempos' => [120, 45, 180, 30], 'tarifa' => 2_400_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-007' => 3, 'MAT-011' => 3, 'MAT-008' => 2, 'MED-005' => 1, 'DIS-007' => 1, 'DIS-009' => 1],
            'equipos' => $sala3, 'personal' => $completo,
        ],
        [
            'cups' => '690200', 'nombre' => 'Legrado uterino obstétrico', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'baja', 'tiempos' => [25, 20, 60, 15], 'tarifa' => 420_000,
            'kits' => ['sedacion', 'menor', 'post_menor'],
            'insumos' => ['MED-001' => 2, 'MED-050' => 2],
            'equipos' => ['EQ-MON-03'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '695100', 'nombre' => 'Aspiración manual endouterina (AMEU)', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'baja', 'tiempos' => [20, 20, 45, 15], 'tarifa' => 380_000,
            'kits' => ['sedacion', 'menor', 'post_menor'],
            'insumos' => ['DIS-053' => 1, 'MED-004' => 1],
            'equipos' => ['EQ-MON-05'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '663200', 'nombre' => 'Ligadura tubárica (pomeroy) por minilaparotomía', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'baja', 'tiempos' => [35, 20, 60, 15], 'tarifa' => 520_000,
            'kits' => ['raquidea', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 2, 'MAT-009' => 1],
            'equipos' => $sala3, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '666200', 'nombre' => 'Salpingectomía por embarazo ectópico', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'alta', 'tiempos' => [80, 30, 120, 25], 'tarifa' => 1_650_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-008' => 2, 'MAT-011' => 2, 'MAT-055' => 1, 'MAT-058' => 2, 'MAT-043' => 1, 'DIS-007' => 1, 'DIS-009' => 1],
            'equipos' => [...$sala3, 'EQ-CFL-01'], 'personal' => $completo,
        ],
        [
            'cups' => '652900', 'nombre' => 'Quistectomía de ovario', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media', 'tiempos' => [75, 30, 120, 25], 'tarifa' => 1_250_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-008' => 2, 'MAT-009' => 1, 'MAT-011' => 1, 'DIS-007' => 1, 'DIS-009' => 1],
            'equipos' => $sala3, 'personal' => $completo,
        ],
        [
            'cups' => '675100', 'nombre' => 'Cerclaje cervical', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'baja', 'tiempos' => [30, 20, 60, 15], 'tarifa' => 560_000,
            'kits' => ['raquidea_obstetrica', 'menor', 'post_menor'],
            'insumos' => ['MAT-015' => 1],
            'post' => ['MED-058' => 2],
            'equipos' => [...$sala3, 'EQ-DOP-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '705000', 'nombre' => 'Colporrafia anterior y posterior', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media', 'tiempos' => [70, 30, 120, 20], 'tarifa' => 1_150_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-012' => 3, 'MAT-008' => 2, 'MED-005' => 1, 'DIS-007' => 1, 'DIS-009' => 1],
            'equipos' => $sala3, 'personal' => $completo,
        ],
        [
            'cups' => '672000', 'nombre' => 'Conización cervical', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'baja', 'tiempos' => [30, 20, 60, 15], 'tarifa' => 620_000,
            'kits' => ['sedacion', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 2, 'MED-005' => 1, 'DIS-032' => 1],
            'equipos' => ['EQ-MON-05', 'EQ-ELB-05'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '756200', 'nombre' => 'Reparación de desgarro perineal grado III-IV', 'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media', 'tiempos' => [45, 15, 90, 15], 'tarifa' => 480_000,
            'kits' => ['raquidea_obstetrica', 'menor', 'post_menor'],
            'pre' => ['MED-042' => 1],
            'insumos' => ['MAT-010' => 2, 'MAT-009' => 2],
            'equipos' => $sala3, 'personal' => $sinAyudante,
        ],

        // ── Ortopedia y traumatología (Sala 4) ────────────────────────────
        [
            'cups' => '793500', 'nombre' => 'Osteosíntesis de fémur con clavo intramedular', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'alta', 'tiempos' => [150, 45, 240, 35], 'tarifa' => 3_100_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['DIS-041' => 1, 'DIS-042' => 4, 'MAT-007' => 2, 'MAT-013' => 2, 'MAT-051' => 2, 'MAT-054' => 2, 'MED-052' => 2, 'MAT-043' => 1],
            'equipos' => [...$sala4, 'EQ-ARC-01', 'EQ-MOR-01', 'EQ-MOT-01', 'EQ-CAL-03'], 'personal' => $completo,
        ],
        [
            'cups' => '793600', 'nombre' => 'Osteosíntesis de tibia con clavo intramedular', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'alta', 'tiempos' => [130, 45, 180, 35], 'tarifa' => 2_800_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['DIS-040' => 1, 'DIS-042' => 4, 'MAT-007' => 1, 'MAT-013' => 2, 'MAT-051' => 2, 'MAT-054' => 2],
            'equipos' => [...$sala4, 'EQ-ARC-01', 'EQ-MOR-01', 'EQ-MOT-01'], 'personal' => $completo,
        ],
        [
            'cups' => '793200', 'nombre' => 'Osteosíntesis de radio distal con placa', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'media', 'tiempos' => [90, 40, 120, 30], 'tarifa' => 1_900_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['DIS-038' => 1, 'DIS-035' => 6, 'MAT-008' => 1, 'MAT-014' => 2, 'MAT-050' => 2, 'MAT-054' => 1],
            'equipos' => [...$sala4, 'EQ-ARC-01', 'EQ-MOT-02', 'EQ-TOR-01'], 'personal' => $completo,
        ],
        [
            'cups' => '793800', 'nombre' => 'Osteosíntesis de fractura bimaleolar de tobillo', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'media', 'tiempos' => [100, 40, 120, 30], 'tarifa' => 2_050_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['DIS-039' => 1, 'DIS-035' => 6, 'DIS-043' => 2, 'MAT-008' => 1, 'MAT-014' => 2, 'MAT-050' => 2, 'MAT-054' => 2],
            'equipos' => [...$sala4, 'EQ-ARC-01', 'EQ-MOT-01', 'EQ-TOR-02'], 'personal' => $completo,
        ],
        [
            'cups' => '786000', 'nombre' => 'Retiro de material de osteosíntesis', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'baja', 'tiempos' => [50, 30, 60, 20], 'tarifa' => 720_000,
            'kits' => ['raquidea', 'menor', 'post_menor'],
            'insumos' => ['MAT-013' => 1, 'MAT-008' => 1, 'MAT-050' => 1],
            'equipos' => [...$sala4, 'EQ-TOR-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '806000', 'nombre' => 'Artroscopia de rodilla con meniscectomía parcial', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'media', 'tiempos' => [70, 40, 90, 30], 'tarifa' => 2_200_000,
            'kits' => ['raquidea', 'mayor', 'post_menor'],
            'insumos' => ['DIS-054' => 1, 'MED-062' => 6, 'MAT-013' => 1, 'MAT-051' => 2, 'MAT-054' => 1],
            'equipos' => [...$sala4, 'EQ-ART-01', 'EQ-TOR-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '841100', 'nombre' => 'Amputación de dedo de la mano', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'baja', 'tiempos' => [40, 20, 60, 20], 'tarifa' => 640_000,
            'kits' => ['local', 'menor', 'post_menor'],
            'insumos' => ['MAT-013' => 1, 'MAT-050' => 1],
            'equipos' => ['EQ-ELB-05'], 'personal' => $local,
        ],
        [
            'cups' => '841500', 'nombre' => 'Amputación infracondílea', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'alta', 'tiempos' => [100, 40, 180, 30], 'tarifa' => 2_250_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'pre' => ['MED-043' => 1],
            'insumos' => ['MAT-007' => 2, 'MAT-013' => 2, 'MAT-017' => 2, 'DIS-026' => 1, 'DIS-034' => 1, 'MAT-051' => 3, 'MAT-054' => 2],
            'equipos' => [...$sala4, 'EQ-MOT-02', 'EQ-TOR-02'], 'personal' => $completo,
        ],
        [
            'cups' => '824400', 'nombre' => 'Tenorrafia de flexores de la mano', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'media', 'tiempos' => [80, 30, 90, 20], 'tarifa' => 1_200_000,
            'kits' => ['general', 'mayor', 'post_menor'],
            'insumos' => ['MAT-016' => 2, 'MAT-014' => 2, 'MAT-050' => 1, 'MAT-052' => 2, 'MAT-054' => 1],
            'equipos' => [...$sala4, 'EQ-TOR-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '790100', 'nombre' => 'Reducción cerrada de fractura con inmovilización en yeso', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'baja', 'tiempos' => [30, 20, 60, 15], 'tarifa' => 450_000,
            'kits' => ['sedacion', 'post_menor'],
            'insumos' => ['MAT-052' => 4, 'MAT-053' => 2, 'MAT-054' => 3, 'MAT-028' => 0.05],
            'equipos' => ['EQ-MON-04', 'EQ-ARC-01'],
            'personal' => ['cirujano' => 1, 'anestesiologo' => 1, 'circulante' => 1],
        ],
        [
            'cups' => '796000', 'nombre' => 'Lavado y desbridamiento de fractura abierta', 'especialidad' => 'Ortopedia y traumatología',
            'complejidad' => 'media', 'tiempos' => [60, 20, 90, 25], 'tarifa' => 980_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'pre' => ['MED-043' => 1, 'MED-044' => 1, 'MED-074' => 1],
            'insumos' => ['MED-062' => 6, 'MAT-055' => 1, 'MAT-051' => 2],
            'equipos' => $sala4, 'personal' => $sinAyudante,
        ],

        // ── Urología ──────────────────────────────────────────────────────
        [
            'cups' => '602900', 'nombre' => 'Resección transuretral de próstata (RTU)', 'especialidad' => 'Urología',
            'complejidad' => 'alta', 'tiempos' => [90, 40, 180, 30], 'tarifa' => 2_600_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'pre' => ['MED-047' => 1],
            'insumos' => ['DIS-047' => 1, 'MED-065' => 6, 'MED-006' => 1, 'DIS-008' => 1, 'DIS-009' => 1],
            'equipos' => [...$sala2, 'EQ-URO-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '603000', 'nombre' => 'Prostatectomía abierta suprapúbica', 'especialidad' => 'Urología',
            'complejidad' => 'alta', 'tiempos' => [150, 45, 240, 30], 'tarifa' => 3_200_000,
            'kits' => ['raquidea', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-011' => 4, 'MAT-007' => 2, 'MAT-008' => 2, 'MAT-055' => 2, 'MAT-043' => 1, 'MED-052' => 2, 'DIS-008' => 1, 'DIS-009' => 1, 'DIS-026' => 1],
            'equipos' => $sala2, 'personal' => $completo,
        ],
        [
            'cups' => '640000', 'nombre' => 'Circuncisión', 'especialidad' => 'Urología',
            'complejidad' => 'baja', 'tiempos' => [30, 20, 45, 15], 'tarifa' => 420_000,
            'kits' => ['local', 'menor', 'post_menor'],
            'insumos' => ['MAT-010' => 1],
            'equipos' => ['EQ-ELB-05'], 'personal' => $local,
        ],
        [
            'cups' => '612000', 'nombre' => 'Hidrocelectomía', 'especialidad' => 'Urología',
            'complejidad' => 'baja', 'tiempos' => [45, 20, 60, 15], 'tarifa' => 650_000,
            'kits' => ['raquidea', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 2, 'MAT-009' => 1, 'DIS-025' => 1],
            'equipos' => $sala5, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '625000', 'nombre' => 'Orquidopexia', 'especialidad' => 'Urología',
            'complejidad' => 'media', 'tiempos' => [50, 30, 60, 20], 'tarifa' => 780_000,
            'kits' => ['general', 'menor', 'post_menor'],
            'insumos' => ['MAT-009' => 2, 'MAT-010' => 1],
            'equipos' => $sala2, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '573200', 'nombre' => 'Cistoscopia diagnóstica', 'especialidad' => 'Urología',
            'complejidad' => 'baja', 'tiempos' => [25, 20, 45, 15], 'tarifa' => 420_000,
            'kits' => ['sedacion', 'post_menor'],
            'insumos' => ['MED-006' => 1, 'MAT-058' => 2, 'MAT-003' => 2],
            'equipos' => ['EQ-MON-02', 'EQ-URO-01'],
            'personal' => ['cirujano' => 1, 'anestesiologo' => 1, 'circulante' => 1],
        ],
        [
            'cups' => '637300', 'nombre' => 'Vasectomía', 'especialidad' => 'Urología',
            'complejidad' => 'baja', 'tiempos' => [25, 15, 30, 15], 'tarifa' => 380_000,
            'kits' => ['local', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 1, 'MAT-014' => 1],
            'equipos' => ['EQ-ELB-05'], 'personal' => $local,
        ],

        // ── Otorrinolaringología ──────────────────────────────────────────
        [
            'cups' => '283000', 'nombre' => 'Amigdalectomía con o sin adenoidectomía', 'especialidad' => 'Otorrinolaringología',
            'complejidad' => 'media', 'tiempos' => [45, 30, 90, 20], 'tarifa' => 980_000,
            'kits' => ['general', 'menor', 'post_menor'],
            'insumos' => ['MAT-012' => 2, 'MED-052' => 1, 'DIS-012' => 1],
            'equipos' => $sala1, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '286000', 'nombre' => 'Adenoidectomía', 'especialidad' => 'Otorrinolaringología',
            'complejidad' => 'baja', 'tiempos' => [30, 30, 60, 15], 'tarifa' => 720_000,
            'kits' => ['general', 'menor', 'post_menor'],
            'insumos' => ['DIS-012' => 1],
            'equipos' => $sala1, 'personal' => $sinAyudante,
        ],
        [
            'cups' => '215000', 'nombre' => 'Septoplastia', 'especialidad' => 'Otorrinolaringología',
            'complejidad' => 'media', 'tiempos' => [70, 30, 90, 20], 'tarifa' => 1_350_000,
            'kits' => ['general', 'menor', 'post_menor'],
            'insumos' => ['MAT-010' => 2, 'MED-005' => 1, 'MAT-059' => 2],
            'equipos' => $sala1, 'personal' => $sinAyudante,
        ],

        // ── Cirugía plástica ──────────────────────────────────────────────
        [
            'cups' => '866000', 'nombre' => 'Injerto de piel de espesor parcial', 'especialidad' => 'Cirugía plástica',
            'complejidad' => 'media', 'tiempos' => [70, 30, 90, 20], 'tarifa' => 1_150_000,
            'kits' => ['general', 'mayor', 'post_mayor'],
            'insumos' => ['MAT-013' => 2, 'MAT-047' => 2, 'MAT-051' => 2, 'MAT-048' => 2, 'DIS-027' => 1],
            'equipos' => $sala1, 'personal' => $completo,
        ],

        // ── Cirugía pediátrica ────────────────────────────────────────────
        [
            'cups' => '530300', 'nombre' => 'Herniotomía inguinal pediátrica', 'especialidad' => 'Cirugía pediátrica',
            'complejidad' => 'baja', 'tiempos' => [45, 30, 60, 15], 'tarifa' => 720_000,
            'kits' => ['general', 'menor', 'post_menor'],
            'insumos' => ['MAT-009' => 1, 'MAT-010' => 1, 'MAT-067' => 1],
            'equipos' => $sala1, 'personal' => $sinAyudante,
        ],

        // ── Oftalmología (Sala 5) ─────────────────────────────────────────
        [
            'cups' => '134100', 'nombre' => 'Facoemulsificación con implante de lente intraocular', 'especialidad' => 'Oftalmología',
            'complejidad' => 'media', 'tiempos' => [40, 30, 60, 20], 'tarifa' => 1_850_000,
            'kits' => ['sedacion', 'menor'],
            'pre' => ['MED-079' => 1],
            'insumos' => ['MED-080' => 1, 'MED-083' => 1, 'DIS-048' => 1, 'DIS-052' => 1, 'MAT-019' => 1, 'MAT-058' => 1],
            'post' => ['MED-081' => 1, 'MED-082' => 1],
            'equipos' => ['EQ-MON-05', 'EQ-MIC-01', 'EQ-FAC-01'], 'personal' => $sinAyudante,
        ],
        [
            'cups' => '113900', 'nombre' => 'Resección de pterigión con autoinjerto conjuntival', 'especialidad' => 'Oftalmología',
            'complejidad' => 'baja', 'tiempos' => [35, 20, 45, 15], 'tarifa' => 620_000,
            'kits' => ['local', 'menor'],
            'insumos' => ['MED-080' => 1, 'MAT-019' => 1, 'MAT-010' => 1],
            'post' => ['MED-081' => 1],
            'equipos' => ['EQ-MIC-01'], 'personal' => $local,
        ],
    ],
];
