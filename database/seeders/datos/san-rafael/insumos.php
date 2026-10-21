<?php

/**
 * Insumos que se añaden al Hospital San Rafael.
 *
 * Catálogo variado de uso hospitalario —anestesia, antibióticos, obstetricia,
 * soluciones, antisépticos, oftalmología, material de curación, suturas,
 * osteosíntesis— y algunos de uso general (glucometría, pañales, residuos).
 * Continúa la numeración de los códigos [SEMILLA] (MED-003, MAT-006, DIS-003).
 *
 * Columnas: código · nombre · categoría · unidad · costo unitario · ATC.
 * Los medicamentos llevan ATC real (obligatorio en el formulario).
 *
 * Precios de compra de referencia 2026 en COP [VERIFICAR con facturas].
 */
return [
    // ── Medicamentos: anestésicos locales ─────────────────────────────────
    ['MED-004', 'Lidocaína 2 % sin epinefrina vial 20 ml', 'medicamento', 'vial', 3_800, 'N01BB02'],
    ['MED-005', 'Lidocaína 2 % con epinefrina vial 20 ml', 'medicamento', 'vial', 5_200, 'N01BB52'],
    ['MED-006', 'Lidocaína jalea 2 % tubo 30 g', 'medicamento', 'tubo', 8_500, 'N01BB02'],
    ['MED-007', 'Bupivacaína pesada 0,5 % (hiperbárica) ampolla 4 ml', 'medicamento', 'ampolla', 11_000, 'N01BB01'],
    ['MED-008', 'Bupivacaína isobárica 0,5 % vial 20 ml', 'medicamento', 'vial', 14_000, 'N01BB01'],
    ['MED-009', 'Ropivacaína 7,5 mg/ml ampolla 20 ml', 'medicamento', 'ampolla', 42_000, 'N01BB09'],
    ['MED-084', 'Lidocaína 10 % spray frasco 50 ml', 'medicamento', 'frasco', 42_000, 'N01BB02'],

    // ── Medicamentos: anestesia general y sedación ────────────────────────
    ['MED-010', 'Propofol 1 % ampolla 20 ml', 'medicamento', 'ampolla', 9_800, 'N01AX10'],
    ['MED-011', 'Ketamina 500 mg/10 ml', 'medicamento', 'vial', 26_000, 'N01AX03'],
    ['MED-012', 'Etomidato 20 mg/10 ml', 'medicamento', 'ampolla', 42_000, 'N01AX07'],
    ['MED-013', 'Sevoflurano frasco 250 ml', 'medicamento', 'frasco', 380_000, 'N01AB08'],
    ['MED-014', 'Midazolam 5 mg/5 ml', 'medicamento', 'ampolla', 3_500, 'N05CD08'],
    ['MED-070', 'Dexmedetomidina 200 mcg/2 ml', 'medicamento', 'vial', 85_000, 'N05CM18'],
    ['MED-071', 'Diazepam 10 mg/2 ml', 'medicamento', 'ampolla', 1_800, 'N05BA01'],

    // ── Medicamentos: opioides y analgésicos ──────────────────────────────
    ['MED-015', 'Fentanilo 0,5 mg/10 ml', 'medicamento', 'ampolla', 6_800, 'N01AH01'],
    ['MED-016', 'Remifentanilo 2 mg', 'medicamento', 'vial', 98_000, 'N01AH06'],
    ['MED-017', 'Morfina 10 mg/ml', 'medicamento', 'ampolla', 3_200, 'N02AA01'],
    ['MED-018', 'Hidromorfona 2 mg/ml', 'medicamento', 'ampolla', 9_500, 'N02AA03'],
    ['MED-019', 'Tramadol 50 mg/ml ampolla 2 ml', 'medicamento', 'ampolla', 1_200, 'N02AX02'],
    ['MED-020', 'Dipirona (metamizol) 1 g/2 ml', 'medicamento', 'ampolla', 1_300, 'N02BB02'],
    ['MED-021', 'Acetaminofén 1 g/100 ml intravenoso', 'medicamento', 'frasco', 18_000, 'N02BE01'],
    ['MED-022', 'Ketorolaco 30 mg/ml', 'medicamento', 'ampolla', 1_200, 'M01AB15'],
    ['MED-023', 'Diclofenaco 75 mg/3 ml', 'medicamento', 'ampolla', 800, 'M01AB05'],

    // ── Medicamentos: relajantes y reversores ─────────────────────────────
    ['MED-024', 'Rocuronio 50 mg/5 ml', 'medicamento', 'vial', 22_000, 'M03AC09'],
    ['MED-025', 'Succinilcolina 100 mg', 'medicamento', 'vial', 12_000, 'M03AB01'],
    ['MED-026', 'Vecuronio 4 mg', 'medicamento', 'vial', 15_000, 'M03AC03'],
    ['MED-027', 'Neostigmina 0,5 mg/ml', 'medicamento', 'ampolla', 3_800, 'N07AA01'],
    ['MED-028', 'Sugammadex 200 mg/2 ml', 'medicamento', 'vial', 380_000, 'V03AB35'],
    ['MED-068', 'Naloxona 0,4 mg/ml', 'medicamento', 'ampolla', 14_000, 'V03AB15'],
    ['MED-069', 'Flumazenil 0,5 mg/5 ml', 'medicamento', 'ampolla', 48_000, 'V03AB25'],

    // ── Medicamentos: vasoactivos y reanimación ───────────────────────────
    ['MED-029', 'Atropina 1 mg/ml', 'medicamento', 'ampolla', 1_600, 'A03BA01'],
    ['MED-030', 'Etilefrina 10 mg/ml', 'medicamento', 'ampolla', 3_200, 'C01CA01'],
    ['MED-031', 'Fenilefrina 10 mg/ml', 'medicamento', 'ampolla', 18_000, 'C01CA06'],
    ['MED-032', 'Efedrina 60 mg/ml', 'medicamento', 'ampolla', 14_000, 'C01CA26'],
    ['MED-033', 'Adrenalina (epinefrina) 1 mg/ml', 'medicamento', 'ampolla', 1_800, 'C01CA24'],
    ['MED-034', 'Noradrenalina 4 mg/4 ml', 'medicamento', 'ampolla', 9_500, 'C01CA03'],
    ['MED-059', 'Gluconato de calcio 10 % ampolla 10 ml', 'medicamento', 'ampolla', 2_800, 'A12AA03'],
    ['MED-060', 'Bicarbonato de sodio 1 mEq/ml ampolla 10 ml', 'medicamento', 'ampolla', 2_900, 'B05XA02'],
    ['MED-061', 'Cloruro de potasio 2 mEq/ml ampolla 10 ml', 'medicamento', 'ampolla', 1_200, 'B05XA01'],

    // ── Medicamentos: corticoides, antieméticos, gastroprotección ────────
    ['MED-035', 'Dexametasona 8 mg/2 ml', 'medicamento', 'ampolla', 2_200, 'H02AB02'],
    ['MED-036', 'Hidrocortisona 100 mg', 'medicamento', 'vial', 7_800, 'H02AB09'],
    ['MED-037', 'Ondansetrón 8 mg/4 ml', 'medicamento', 'ampolla', 1_900, 'A04AA01'],
    ['MED-038', 'Metoclopramida 10 mg/2 ml', 'medicamento', 'ampolla', 900, 'A03FA01'],
    ['MED-039', 'Omeprazol 40 mg intravenoso', 'medicamento', 'vial', 8_500, 'A02BC01'],

    // ── Medicamentos: antibióticos ────────────────────────────────────────
    ['MED-040', 'Ampicilina + sulbactam 1,5 g', 'medicamento', 'vial', 9_500, 'J01CR01'],
    ['MED-041', 'Ceftriaxona 1 g', 'medicamento', 'vial', 4_800, 'J01DD04'],
    ['MED-042', 'Metronidazol 500 mg/100 ml', 'medicamento', 'bolsa', 4_200, 'J01XD01'],
    ['MED-043', 'Clindamicina 600 mg/4 ml', 'medicamento', 'ampolla', 6_800, 'J01FF01'],
    ['MED-044', 'Gentamicina 80 mg/2 ml', 'medicamento', 'ampolla', 1_100, 'J01GB03'],
    ['MED-045', 'Vancomicina 500 mg', 'medicamento', 'vial', 12_000, 'J01XA01'],
    ['MED-046', 'Piperacilina + tazobactam 4,5 g', 'medicamento', 'vial', 32_000, 'J01CR05'],
    ['MED-047', 'Ciprofloxacina 200 mg/100 ml intravenosa', 'medicamento', 'bolsa', 9_000, 'J01MA02'],

    // ── Medicamentos: obstetricia ─────────────────────────────────────────
    ['MED-048', 'Carbetocina 100 mcg/ml', 'medicamento', 'ampolla', 95_000, 'H01BB03'],
    ['MED-049', 'Metilergonovina 0,2 mg/ml', 'medicamento', 'ampolla', 4_500, 'G02AB01'],
    ['MED-050', 'Misoprostol 200 mcg', 'medicamento', 'tableta', 4_800, 'G02AD06'],
    ['MED-051', 'Sulfato de magnesio 20 % ampolla 10 ml', 'medicamento', 'ampolla', 2_500, 'A12CC02'],
    ['MED-056', 'Labetalol 100 mg/20 ml', 'medicamento', 'ampolla', 85_000, 'C07AG01'],
    ['MED-057', 'Hidralazina 20 mg/ml', 'medicamento', 'ampolla', 38_000, 'C02DB02'],
    ['MED-058', 'Nifedipino 10 mg', 'medicamento', 'cápsula', 250, 'C08CA05'],
    ['MED-067', 'Fitomenadiona (vitamina K) 1 mg neonatal', 'medicamento', 'ampolla', 2_500, 'B02BA01'],

    // ── Medicamentos: hemostasia y anticoagulación ────────────────────────
    ['MED-052', 'Ácido tranexámico 500 mg/5 ml', 'medicamento', 'ampolla', 3_900, 'B02AA02'],
    ['MED-053', 'Heparina sódica 5.000 UI/ml vial 5 ml', 'medicamento', 'vial', 18_000, 'B01AB01'],
    ['MED-054', 'Enoxaparina 40 mg jeringa prellenada', 'medicamento', 'jeringa', 14_500, 'B01AB05'],
    ['MED-055', 'Furosemida 20 mg/2 ml', 'medicamento', 'ampolla', 900, 'C03CA01'],

    // ── Medicamentos: soluciones y gases ──────────────────────────────────
    ['MED-062', 'Cloruro de sodio 0,9 % bolsa 500 ml', 'medicamento', 'bolsa', 3_200, 'B05BB01'],
    ['MED-063', 'Lactato de Ringer (Hartmann) bolsa 500 ml', 'medicamento', 'bolsa', 3_500, 'B05BB01'],
    ['MED-064', 'Dextrosa 5 % en agua bolsa 500 ml', 'medicamento', 'bolsa', 3_400, 'B05BA03'],
    ['MED-065', 'Glicina 1,5 % para irrigación bolsa 3.000 ml', 'medicamento', 'bolsa', 38_000, 'B05CX03'],
    ['MED-066', 'Oxígeno medicinal', 'medicamento', 'm3', 9_800, 'V03AN01'],

    // ── Medicamentos: otros de uso general ────────────────────────────────
    ['MED-072', 'Salbutamol inhalador 100 mcg/dosis', 'medicamento', 'inhalador', 9_800, 'R03AC02'],
    ['MED-073', 'Insulina cristalina 100 UI/ml vial 10 ml', 'medicamento', 'vial', 28_000, 'A10AB01'],
    ['MED-074', 'Toxoide tetánico y diftérico (Td)', 'medicamento', 'dosis', 6_500, 'J07AM51'],

    // ── Medicamentos: antisépticos ────────────────────────────────────────
    ['MED-075', 'Clorhexidina 2 % + alcohol 70 % frasco 500 ml', 'medicamento', 'frasco', 28_000, 'D08AC02'],
    ['MED-076', 'Povidona yodada solución 10 % frasco 120 ml', 'medicamento', 'frasco', 9_000, 'D08AG02'],
    ['MED-077', 'Povidona yodada espuma 8 % frasco 120 ml', 'medicamento', 'frasco', 9_500, 'D08AG02'],
    ['MED-078', 'Alcohol etílico 70 % frasco 700 ml', 'medicamento', 'frasco', 9_500, 'D08AX08'],

    // ── Medicamentos: oftalmología ────────────────────────────────────────
    ['MED-079', 'Tropicamida + fenilefrina colirio 5 ml', 'medicamento', 'frasco', 32_000, 'S01FA56'],
    ['MED-080', 'Tetracaína colirio 0,5 % 10 ml', 'medicamento', 'frasco', 18_000, 'S01HA03'],
    ['MED-081', 'Moxifloxacino colirio 0,5 % 5 ml', 'medicamento', 'frasco', 38_000, 'S01AE07'],
    ['MED-082', 'Prednisolona acetato colirio 1 % 5 ml', 'medicamento', 'frasco', 29_000, 'S01BA04'],
    ['MED-083', 'Hialuronato de sodio 1 % viscoelástico jeringa', 'medicamento', 'jeringa', 120_000, 'S01KA01'],

    // ── Material: suturas ─────────────────────────────────────────────────
    ['MAT-007', 'Sutura poliglactina 910 (Vicryl) 0 aguja CT-1', 'material', 'sobre', 18_000, null],
    ['MAT-008', 'Sutura poliglactina 910 (Vicryl) 2-0 aguja SH', 'material', 'sobre', 16_000, null],
    ['MAT-009', 'Sutura poliglactina 910 (Vicryl) 3-0 aguja SH', 'material', 'sobre', 15_500, null],
    ['MAT-010', 'Sutura poliglactina rápida (Vicryl Rapide) 4-0', 'material', 'sobre', 17_500, null],
    ['MAT-011', 'Sutura catgut crómico 1 aguja CT', 'material', 'sobre', 9_500, null],
    ['MAT-012', 'Sutura catgut crómico 2-0', 'material', 'sobre', 8_800, null],
    ['MAT-013', 'Sutura nylon monofilamento 3-0', 'material', 'sobre', 6_500, null],
    ['MAT-014', 'Sutura nylon monofilamento 4-0', 'material', 'sobre', 7_000, null],
    ['MAT-015', 'Sutura polipropileno (Prolene) 0', 'material', 'sobre', 22_000, null],
    ['MAT-016', 'Sutura polipropileno (Prolene) 2-0', 'material', 'sobre', 19_500, null],
    ['MAT-017', 'Sutura seda trenzada 2-0', 'material', 'sobre', 5_800, null],
    ['MAT-018', 'Sutura polidioxanona (PDS) loop 1', 'material', 'sobre', 48_000, null],
    ['MAT-019', 'Sutura nylon 10-0 oftálmica', 'material', 'sobre', 45_000, null],

    // ── Material: corte y protección del personal ─────────────────────────
    ['MAT-020', 'Hoja de bisturí No. 10', 'material', 'unidad', 900, null],
    ['MAT-021', 'Hoja de bisturí No. 15', 'material', 'unidad', 900, null],
    ['MAT-022', 'Hoja de bisturí No. 23', 'material', 'unidad', 950, null],
    ['MAT-023', 'Bata quirúrgica desechable estéril', 'material', 'unidad', 9_500, null],
    ['MAT-024', 'Gorro desechable', 'material', 'unidad', 450, null],
    ['MAT-025', 'Tapabocas quirúrgico', 'material', 'unidad', 350, null],
    ['MAT-026', 'Respirador N95', 'material', 'unidad', 3_200, null],
    ['MAT-027', 'Polainas desechables', 'material', 'par', 600, null],
    ['MAT-028', 'Guantes de examen de nitrilo caja x 100', 'material', 'caja', 24_000, null],
    ['MAT-029', 'Guantes estériles talla 6½', 'material', 'par', 2_800, null],
    ['MAT-030', 'Guantes estériles talla 7½', 'material', 'par', 2_800, null],
    ['MAT-031', 'Guantes estériles talla 8', 'material', 'par', 2_800, null],

    // ── Material: campos y ropa ───────────────────────────────────────────
    ['MAT-032', 'Campo de cesárea con bolsa colectora', 'material', 'unidad', 38_000, null],
    ['MAT-033', 'Cubierta de mesa de Mayo', 'material', 'unidad', 4_800, null],
    ['MAT-055', 'Compresa de laparotomía 45 x 45 cm con trazador', 'material', 'paquete', 14_000, null],
    ['MAT-063', 'Sábana desechable para camilla', 'material', 'unidad', 2_600, null],
    ['MAT-066', 'Cubierta estéril para cámara de laparoscopia', 'material', 'unidad', 12_000, null],

    // ── Material: aplicación de medicamentos ──────────────────────────────
    ['MAT-034', 'Jeringa desechable 5 ml', 'material', 'unidad', 450, null],
    ['MAT-035', 'Jeringa desechable 10 ml', 'material', 'unidad', 600, null],
    ['MAT-036', 'Jeringa desechable 20 ml', 'material', 'unidad', 950, null],
    ['MAT-037', 'Jeringa de insulina 1 ml', 'material', 'unidad', 350, null],
    ['MAT-038', 'Aguja hipodérmica 21G', 'material', 'unidad', 180, null],
    ['MAT-039', 'Aguja hipodérmica 18G', 'material', 'unidad', 200, null],
    ['MAT-040', 'Equipo de venoclisis macrogoteo', 'material', 'unidad', 2_800, null],
    ['MAT-041', 'Equipo de venoclisis microgoteo', 'material', 'unidad', 3_100, null],
    ['MAT-042', 'Equipo para bomba de infusión', 'material', 'unidad', 18_000, null],
    ['MAT-043', 'Equipo de transfusión sanguínea', 'material', 'unidad', 5_500, null],
    ['MAT-044', 'Llave de tres vías', 'material', 'unidad', 1_600, null],
    ['MAT-045', 'Electrodo de ECG adulto', 'material', 'unidad', 500, null],

    // ── Material: curación e inmovilización ───────────────────────────────
    ['MAT-046', 'Esparadrapo de tela 2" rollo', 'material', 'rollo', 6_500, null],
    ['MAT-047', 'Cinta microporosa 1" rollo', 'material', 'rollo', 2_900, null],
    ['MAT-048', 'Apósito transparente 10 x 12 cm', 'material', 'unidad', 3_800, null],
    ['MAT-049', 'Apósito quirúrgico adhesivo 10 x 25 cm', 'material', 'unidad', 4_500, null],
    ['MAT-050', 'Venda elástica 4"', 'material', 'unidad', 3_200, null],
    ['MAT-051', 'Venda elástica 6"', 'material', 'unidad', 4_200, null],
    ['MAT-052', 'Venda de yeso 4"', 'material', 'unidad', 5_800, null],
    ['MAT-053', 'Venda de yeso 6"', 'material', 'unidad', 7_900, null],
    ['MAT-054', 'Algodón laminado (huata) 4"', 'material', 'rollo', 2_400, null],
    ['MAT-067', 'Cierre cutáneo adhesivo (Steri-Strip)', 'material', 'sobre', 7_800, null],

    // ── Material: varios de uso hospitalario ──────────────────────────────
    ['MAT-056', 'Tubo de succión con cánula Yankauer', 'material', 'unidad', 6_200, null],
    ['MAT-057', 'Cinta indicadora para autoclave', 'material', 'rollo', 18_000, null],
    ['MAT-058', 'Agua estéril para irrigación 1.000 ml', 'material', 'frasco', 6_800, null],
    ['MAT-059', 'Gel lubricante estéril sobre 5 g', 'material', 'sobre', 2_300, null],
    ['MAT-060', 'Tira reactiva de glucometría', 'material', 'unidad', 1_100, null],
    ['MAT-061', 'Lanceta para glucometría', 'material', 'unidad', 250, null],
    ['MAT-062', 'Pañal desechable adulto', 'material', 'unidad', 1_900, null],
    ['MAT-064', 'Bolsa roja para residuos peligrosos', 'material', 'unidad', 700, null],
    ['MAT-065', 'Guardián para cortopunzantes 2,9 L', 'material', 'unidad', 9_800, null],

    // ── Dispositivos: accesos y sondas ────────────────────────────────────
    ['DIS-004', 'Catéter intravenoso periférico 18G', 'dispositivo', 'unidad', 2_200, null],
    ['DIS-005', 'Catéter intravenoso periférico 20G', 'dispositivo', 'unidad', 2_000, null],
    ['DIS-006', 'Catéter intravenoso periférico 22G', 'dispositivo', 'unidad', 2_000, null],
    ['DIS-049', 'Catéter venoso central triple lumen 7 Fr', 'dispositivo', 'kit', 120_000, null],
    ['DIS-007', 'Sonda Foley 2 vías 14 Fr', 'dispositivo', 'unidad', 4_500, null],
    ['DIS-008', 'Sonda Foley 3 vías 22 Fr', 'dispositivo', 'unidad', 15_000, null],
    ['DIS-009', 'Bolsa recolectora de orina 2.000 ml', 'dispositivo', 'unidad', 3_000, null],
    ['DIS-010', 'Sonda nasogástrica 16 Fr', 'dispositivo', 'unidad', 2_200, null],
    ['DIS-011', 'Sonda Nelaton 12 Fr', 'dispositivo', 'unidad', 1_100, null],

    // ── Dispositivos: vía aérea y anestesia ───────────────────────────────
    ['DIS-012', 'Tubo endotraqueal con balón 7,0', 'dispositivo', 'unidad', 7_500, null],
    ['DIS-013', 'Tubo endotraqueal con balón 7,5', 'dispositivo', 'unidad', 7_500, null],
    ['DIS-014', 'Máscara laríngea desechable No. 4', 'dispositivo', 'unidad', 48_000, null],
    ['DIS-015', 'Cánula orofaríngea (Guedel)', 'dispositivo', 'unidad', 2_500, null],
    ['DIS-016', 'Circuito de anestesia adulto desechable', 'dispositivo', 'unidad', 28_000, null],
    ['DIS-017', 'Filtro intercambiador de calor y humedad (HME)', 'dispositivo', 'unidad', 6_500, null],
    ['DIS-018', 'Cánula nasal de oxígeno adulto', 'dispositivo', 'unidad', 2_300, null],
    ['DIS-019', 'Máscara de no reinhalación', 'dispositivo', 'unidad', 6_800, null],
    ['DIS-022', 'Aguja espinal Quincke 25G', 'dispositivo', 'unidad', 9_500, null],
    ['DIS-023', 'Aguja espinal punta de lápiz (Whitacre) 27G', 'dispositivo', 'unidad', 28_000, null],
    ['DIS-024', 'Kit de anestesia epidural (Tuohy 18G + catéter)', 'dispositivo', 'kit', 95_000, null],

    // ── Dispositivos: cirugía general y laparoscopia ──────────────────────
    ['DIS-020', 'Placa neutra (de retorno) para electrobisturí', 'dispositivo', 'unidad', 9_000, null],
    ['DIS-021', 'Lápiz de electrobisturí desechable', 'dispositivo', 'unidad', 14_000, null],
    ['DIS-025', 'Dren de Penrose 1/2"', 'dispositivo', 'unidad', 4_800, null],
    ['DIS-026', 'Dren de succión cerrada (Jackson-Pratt)', 'dispositivo', 'unidad', 85_000, null],
    ['DIS-027', 'Grapadora de piel desechable 35 grapas', 'dispositivo', 'unidad', 62_000, null],
    ['DIS-028', 'Trócar desechable 10/12 mm', 'dispositivo', 'unidad', 210_000, null],
    ['DIS-029', 'Trócar desechable 5 mm', 'dispositivo', 'unidad', 180_000, null],
    ['DIS-030', 'Bolsa de extracción laparoscópica', 'dispositivo', 'unidad', 95_000, null],
    ['DIS-031', 'Aguja de Veress', 'dispositivo', 'unidad', 85_000, null],
    ['DIS-032', 'Esponja hemostática de gelatina', 'dispositivo', 'unidad', 38_000, null],
    ['DIS-033', 'Celulosa oxidada regenerada (Surgicel) 5 x 7,5 cm', 'dispositivo', 'unidad', 95_000, null],
    ['DIS-051', 'Bolsa de colostomía drenable', 'dispositivo', 'unidad', 18_000, null],
    ['DIS-055', 'Malla de polipropileno 6 x 11 cm', 'dispositivo', 'unidad', 95_000, null],

    // ── Dispositivos: ortopedia y osteosíntesis ───────────────────────────
    ['DIS-034', 'Cera para hueso', 'dispositivo', 'sobre', 18_000, null],
    ['DIS-035', 'Tornillo cortical 3,5 mm', 'dispositivo', 'unidad', 38_000, null],
    ['DIS-036', 'Tornillo cortical 4,5 mm', 'dispositivo', 'unidad', 42_000, null],
    ['DIS-037', 'Placa DCP 4,5 mm de 8 orificios', 'dispositivo', 'unidad', 420_000, null],
    ['DIS-038', 'Placa LCP de radio distal', 'dispositivo', 'unidad', 1_350_000, null],
    ['DIS-039', 'Placa tercio de tubo 6 orificios', 'dispositivo', 'unidad', 280_000, null],
    ['DIS-040', 'Clavo intramedular bloqueado de tibia', 'dispositivo', 'unidad', 2_400_000, null],
    ['DIS-041', 'Clavo intramedular bloqueado de fémur', 'dispositivo', 'unidad', 2_650_000, null],
    ['DIS-042', 'Tornillo de bloqueo 5,0 mm', 'dispositivo', 'unidad', 120_000, null],
    ['DIS-043', 'Tornillo canulado 7,0 mm', 'dispositivo', 'unidad', 280_000, null],
    ['DIS-044', 'Clavo de Kirschner 1,6 mm', 'dispositivo', 'unidad', 12_000, null],
    ['DIS-045', 'Alambre de cerclaje 1,0 mm', 'dispositivo', 'unidad', 18_000, null],
    ['DIS-046', 'Fijador externo tubular (marco completo)', 'dispositivo', 'kit', 2_800_000, null],
    ['DIS-054', 'Hoja de shaver artroscópico', 'dispositivo', 'unidad', 450_000, null],

    // ── Dispositivos: urología, obstetricia y oftalmología ────────────────
    ['DIS-047', 'Asa de resección para resectoscopio (RTU)', 'dispositivo', 'unidad', 185_000, null],
    ['DIS-050', 'Pinza umbilical (clamp)', 'dispositivo', 'unidad', 900, null],
    ['DIS-053', 'Cánula de Karman desechable 6 mm (AMEU)', 'dispositivo', 'unidad', 18_000, null],
    ['DIS-048', 'Lente intraocular plegable', 'dispositivo', 'unidad', 280_000, null],
    ['DIS-052', 'Cuchillete oftálmico 2,75 mm', 'dispositivo', 'unidad', 65_000, null],
];
