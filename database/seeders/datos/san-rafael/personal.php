<?php

/**
 * Personal quirúrgico que se añade al Hospital San Rafael.
 *
 * Personas ficticias con nombres verosímiles de la región. Dimensionado para
 * cinco salas en jornada de 12 h × 26 días: con los diez del catálogo
 * [SEMILLA] quedan 21 cirujanos de 8 especialidades, 10 ayudantes, 8
 * anestesiólogos, 10 instrumentadores y 12 circulantes.
 *
 * Columnas: nombre · rol · especialidad · salario u honorario mensual ·
 * vinculación · costos indirectos mensuales · activo.
 *
 * Vinculación:
 *   planta → prestaciones = 48 % del salario (cesantías, primas, vacaciones,
 *            salud, pensión, ARL y parafiscales).
 *   ops    → contrato de prestación de servicios: honorario más alto y sin
 *            prestaciones (las paga el contratista).
 *
 * Honorarios de referencia 2026 en COP [VERIFICAR con la nómina del hospital].
 */
return [
    // ── Cirujanos ─────────────────────────────────────────────────────────
    ['Dr. Hernán Darío Pimienta Brugés', 'cirujano', 'Cirugía general', 12_400_000, 'planta', 1_000_000, true],
    ['Dra. Yaneth Patricia Fince Uriana', 'cirujano', 'Cirugía general', 16_500_000, 'ops', 900_000, true],
    ['Dr. Álvaro José Choles Mendoza', 'cirujano', 'Cirugía general', 12_100_000, 'planta', 1_000_000, true],
    ['Dr. Wilmer Enrique Gouriyú Arregocés', 'cirujano', 'Cirugía general', 16_000_000, 'ops', 900_000, true],
    ['Dr. Rafael Antonio Ustáriz Plata', 'cirujano', 'Cirugía general', 12_000_000, 'planta', 1_000_000, false], // retirado (pensión)
    ['Dra. Luz Marina Toncel Zúñiga', 'cirujano', 'Ginecobstetricia', 12_600_000, 'planta', 1_000_000, true],
    ['Dr. Édgar Antonio Bolaño Daza', 'cirujano', 'Ginecobstetricia', 12_300_000, 'planta', 1_000_000, true],
    ['Dra. Katherine Paola Jusayú Romero', 'cirujano', 'Ginecobstetricia', 16_800_000, 'ops', 900_000, true],
    ['Dr. Rubén Darío Vence Ospino', 'cirujano', 'Ginecobstetricia', 15_900_000, 'ops', 900_000, true],
    ['Dra. Marelvis Esther Amaya Peralta', 'cirujano', 'Ginecobstetricia', 12_000_000, 'planta', 1_000_000, true],
    ['Dr. Jaime Alberto Fragozo Mejía', 'cirujano', 'Ortopedia y traumatología', 18_500_000, 'ops', 1_100_000, true],
    ['Dr. Carlos Mario Aragón Barros', 'cirujano', 'Ortopedia y traumatología', 13_200_000, 'planta', 1_100_000, true],
    ['Dr. Andrés Felipe Rois Gómez', 'cirujano', 'Ortopedia y traumatología', 18_000_000, 'ops', 1_100_000, true],
    ['Dr. Iván José Epinayú Pinto', 'cirujano', 'Ortopedia y traumatología', 17_600_000, 'ops', 1_100_000, true],
    ['Dr. Fernando Elías Cuello Lacouture', 'cirujano', 'Urología', 17_800_000, 'ops', 1_000_000, true],
    ['Dr. Javier Enrique Molina Sierra', 'cirujano', 'Urología', 17_200_000, 'ops', 1_000_000, true],
    ['Dra. Silvia Rosa Daza Maestre', 'cirujano', 'Otorrinolaringología', 16_400_000, 'ops', 900_000, true],
    ['Dr. Mauricio Andrés Oñate Castro', 'cirujano', 'Cirugía plástica', 18_200_000, 'ops', 1_000_000, true],
    ['Dra. Natalia Isabel Mendoza Aarón', 'cirujano', 'Cirugía pediátrica', 17_000_000, 'ops', 1_000_000, true],
    ['Dr. Gustavo Adolfo Celedón Ariza', 'cirujano', 'Oftalmología', 17_500_000, 'ops', 1_000_000, true],

    // ── Ayudantes quirúrgicos (médicos generales) ─────────────────────────
    ['Dr. Jhon Jairo Ipuana Barros', 'ayudante', 'Medicina general', 7_100_000, 'planta', 450_000, true],
    ['Dra. Diana Carolina Pushaina Medina', 'ayudante', 'Medicina general', 8_200_000, 'ops', 400_000, true],
    ['Dr. Kevin Alfonso Redondo Gutiérrez', 'ayudante', 'Medicina general', 7_000_000, 'planta', 450_000, true],
    ['Dra. María Fernanda Iguarán Lubo', 'ayudante', 'Medicina general', 8_400_000, 'ops', 400_000, true],
    ['Dr. Óscar Iván Arpushana Solano', 'ayudante', 'Medicina general', 7_200_000, 'planta', 450_000, true],
    ['Dra. Laura Vanessa Cotes Fuentes', 'ayudante', 'Medicina general', 8_000_000, 'ops', 400_000, true],
    ['Dr. Yeison David Mengual Rangel', 'ayudante', 'Medicina general', 8_300_000, 'ops', 400_000, true],
    ['Dra. Shirley Johana Uriana Epieyú', 'ayudante', 'Medicina general', 7_100_000, 'planta', 450_000, true],

    // ── Anestesiólogos ────────────────────────────────────────────────────
    ['Dr. Ricardo José Lacouture Pinedo', 'anestesiologo', 'Anestesiología', 19_500_000, 'ops', 1_000_000, true],
    ['Dra. Adriana Marcela Freyle Gámez', 'anestesiologo', 'Anestesiología', 14_200_000, 'planta', 1_000_000, true],
    ['Dr. Juan Pablo Gnecco Daza', 'anestesiologo', 'Anestesiología', 19_000_000, 'ops', 1_000_000, true],
    ['Dra. Carolina Andrea Ospino Torres', 'anestesiologo', 'Anestesiología', 18_600_000, 'ops', 1_000_000, true],
    ['Dr. Luis Eduardo Deluque Brito', 'anestesiologo', 'Anestesiología', 14_000_000, 'planta', 1_000_000, true],
    ['Dr. Alexander Mejía Arpushana', 'anestesiologo', 'Anestesiología', 18_800_000, 'ops', 1_000_000, true],

    // ── Instrumentadores quirúrgicos ──────────────────────────────────────
    ['Inst. Yuranis Paola Gómez Epiayú', 'instrumentador', 'Instrumentación quirúrgica', 4_100_000, 'planta', 350_000, true],
    ['Inst. Daniela Sofía Solano Pana', 'instrumentador', 'Instrumentación quirúrgica', 4_600_000, 'ops', 300_000, true],
    ['Inst. Eliana Marcela Romero Choles', 'instrumentador', 'Instrumentación quirúrgica', 4_100_000, 'planta', 350_000, true],
    ['Inst. Jorge Luis Movil Toncel', 'instrumentador', 'Instrumentación quirúrgica', 4_600_000, 'ops', 300_000, true],
    ['Inst. Karen Lizeth Barros Ipuana', 'instrumentador', 'Instrumentación quirúrgica', 4_200_000, 'planta', 350_000, true],
    ['Inst. Andrea Carolina Zúñiga Fince', 'instrumentador', 'Instrumentación quirúrgica', 4_500_000, 'ops', 300_000, true],
    ['Inst. Brayan Steven Pinto Uriana', 'instrumentador', 'Instrumentación quirúrgica', 4_600_000, 'ops', 300_000, true],
    ['Inst. Tatiana Margarita Ojeda Vence', 'instrumentador', 'Instrumentación quirúrgica', 4_100_000, 'planta', 350_000, true],

    // ── Circulantes (enfermería) ──────────────────────────────────────────
    ['Enf. Liliana Patricia Daza Arregocés', 'circulante', 'Enfermería profesional', 5_200_000, 'planta', 400_000, true],
    ['Enf. Ana Milena Gouriyú Peralta', 'circulante', 'Enfermería profesional', 5_800_000, 'ops', 350_000, true],
    ['Aux. Rosa Elvira Jusayú Pimienta', 'circulante', 'Auxiliar de enfermería', 2_600_000, 'planta', 250_000, true],
    ['Aux. Yesenia María Epinayú Bolaño', 'circulante', 'Auxiliar de enfermería', 2_900_000, 'ops', 200_000, true],
    ['Aux. Luis Ángel Fragozo Mendoza', 'circulante', 'Auxiliar de enfermería', 2_600_000, 'planta', 250_000, true],
    ['Aux. Dayana Esther Amaya Cuello', 'circulante', 'Auxiliar de enfermería', 2_900_000, 'ops', 200_000, true],
    ['Aux. Mileidys Judith Arpushana Rois', 'circulante', 'Auxiliar de enfermería', 2_600_000, 'planta', 250_000, true],
    ['Aux. Carlos Andrés Ospino Vence', 'circulante', 'Auxiliar de enfermería', 2_900_000, 'ops', 200_000, true],
    ['Aux. Leidis Johana Toncel Pana', 'circulante', 'Auxiliar de enfermería', 2_600_000, 'planta', 250_000, true],
    ['Aux. Wendy Paola Brugés Ipuana', 'circulante', 'Auxiliar de enfermería', 2_600_000, 'planta', 250_000, false], // licencia de maternidad
];
