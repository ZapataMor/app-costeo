<?php

/**
 * Salas que se añaden a la central quirúrgica del Hospital San Rafael.
 *
 * Con las dos salas [SEMILLA] del catálogo quedan cinco: dos de cirugía
 * general y laparoscopia, una obstétrica, una de ortopedia y una de
 * procedimientos menores y oftalmología. Es la dotación típica de un
 * hospital de mediana complejidad de La Guajira [VERIFICAR con el hospital].
 *
 * `costo_hora` = infraestructura imputable a la sala (área, aire, iluminación,
 * gases): no incluye los equipos biomédicos, que se costean aparte.
 */
return [
    [
        'nombre' => 'Sala 3 - Obstétrica',
        'ubicacion' => 'Piso 2 - Central obstétrica',
        'costo_hora' => 42_000,
        'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'máquina de anestesia', 'monitor multiparámetro', 'electrobisturí', 'cuna de calor radiante', 'doppler fetal'],
    ],
    [
        'nombre' => 'Sala 4 - Ortopedia',
        'ubicacion' => 'Piso 2 - Central quirúrgica',
        'costo_hora' => 52_000,
        'equipamiento' => ['lámpara cielítica', 'mesa ortopédica con tracción', 'máquina de anestesia', 'monitor multiparámetro', 'electrobisturí', 'arco en C', 'torniquete neumático', 'torre de artroscopia', 'flujo laminar'],
    ],
    [
        'nombre' => 'Sala 5 - Procedimientos menores',
        'ubicacion' => 'Piso 1 - Cirugía ambulatoria',
        'costo_hora' => 26_000,
        'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'monitor multiparámetro', 'electrobisturí', 'microscopio oftalmológico', 'facoemulsificador'],
    ],
];
