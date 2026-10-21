<?php

/**
 * Contenido de prueba de la Capa 4 para el Hospital San Rafael.
 *
 * Personas: cuentas de profesional atadas a registros de recurso humano de
 * la carga de volumen. Lecciones e hilos: textos verosímiles de un servicio
 * de cirugía de mediana complejidad, sin pacientes ni datos clínicos
 * identificables. Todo es ficticio [SEMILLA-CARGA].
 *
 * `autor` es la clave de la lista de profesionales ('admin' = administrador).
 * `mes` es el mes de publicación (o de creación si no se publicó).
 */
return [
    'profesionales' => [
        'toncel' => ['Dra. Luz Marina Toncel Zúñiga', 'luz.toncel@sanrafael.test'],
        'bolano' => ['Dr. Édgar Antonio Bolaño Daza', 'edgar.bolano@sanrafael.test'],
        'pimienta' => ['Dr. Hernán Darío Pimienta Brugés', 'hernan.pimienta@sanrafael.test'],
        'fince' => ['Dra. Yaneth Patricia Fince Uriana', 'yaneth.fince@sanrafael.test'],
        'aragon' => ['Dr. Carlos Mario Aragón Barros', 'carlos.aragon@sanrafael.test'],
        'cuello' => ['Dr. Fernando Elías Cuello Lacouture', 'fernando.cuello@sanrafael.test'],
        'freyle' => ['Dra. Adriana Marcela Freyle Gámez', 'adriana.freyle@sanrafael.test'],
        'lacouture' => ['Dr. Ricardo José Lacouture Pinedo', 'ricardo.lacouture@sanrafael.test'],
        'daza' => ['Enf. Liliana Patricia Daza Arregocés', 'liliana.daza@sanrafael.test'],
        'gomez' => ['Inst. Yuranis Paola Gómez Epiayú', 'yuranis.gomez@sanrafael.test'],
        'celedon' => ['Dr. Gustavo Adolfo Celedón Ariza', 'gustavo.celedon@sanrafael.test'],
        'mendoza' => ['Dra. Natalia Isabel Mendoza Aarón', 'natalia.mendoza@sanrafael.test'],
    ],

    'lecciones' => [
        [
            'autor' => 'toncel', 'mes' => '2026-05', 'estado' => 'publicada', 'red' => true,
            'tipo' => 'leccion', 'categoria' => 'costos', 'causa' => 'tiempo_quirurgico_prolongado', 'cups' => '740001',
            'titulo' => 'La cesárea base se programa con 120 minutos y se opera en 75',
            'contexto' => 'Al comparar las cesáreas segmentarias con su costo estándar, más de un tercio quedó en sobrecosto alto. El protocolo del procedimiento estaba fijado en 120 minutos de sala, herencia del caso de referencia de costeo, pero la mediana real de las cesáreas programadas es de 70 a 80 minutos y la sala queda reservada el doble.',
            'leccion' => 'Un estándar mal fijado no solo distorsiona la desviación: bloquea la sala obstétrica en la programación. Las cesáreas urgentes de la noche terminaban esperando una sala que figuraba ocupada.',
            'recomendacion' => 'Ajustar la duración estimada de la cesárea segmentaria a 80 minutos y revisar cada trimestre los procedimientos de alto volumen con su mediana real.',
            'etiquetas' => ['programación', 'estándar'], 'impacto' => 4_600_000,
        ],
        [
            'autor' => 'freyle', 'mes' => '2026-05', 'estado' => 'publicada', 'red' => true,
            'tipo' => 'mejor_practica', 'categoria' => 'costos', 'causa' => 'consumo_excesivo_insumos', 'cups' => null,
            'titulo' => 'Sevoflurano: flujos bajos de gas fresco después de la inducción',
            'contexto' => 'El consumo de sevoflurano por hora de anestesia general variaba casi al doble entre salas. Las salas con mayor consumo mantenían flujos de gas fresco de 2 a 3 L/min durante todo el mantenimiento.',
            'leccion' => 'Con flujos de 0,5 a 1 L/min tras la inducción y la monitoría de gases que ya tienen las máquinas, el consumo baja sin cambiar la profundidad anestésica. El frasco de 250 ml es uno de los insumos más caros del kit de anestesia general.',
            'recomendacion' => 'Estandarizar anestesia de bajo flujo en el protocolo de anestesia general y revisar el consumo mensual por sala en el tablero de mejores prácticas.',
            'etiquetas' => ['anestesia', 'gases'], 'impacto' => 2_300_000,
        ],
        [
            'autor' => 'daza', 'mes' => '2026-06', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'proceso', 'causa' => 'retraso_alistamiento', 'cups' => null,
            'titulo' => 'El recambio de la Sala 1 se alarga cuando falta el kit de ropa estéril',
            'contexto' => 'En junio varias cirugías programadas de la Sala 1 empezaron 30 a 45 minutos tarde. En todos los casos el paquete de ropa quirúrgica no había llegado de la central de esterilización al terminar el caso anterior.',
            'leccion' => 'El cuello de botella no era el aseo de la sala sino la entrega de ropa estéril. Lavandería y esterilización despachan por lotes de mañana y tarde, no por caso.',
            'recomendacion' => 'Dejar dos paquetes de ropa de reserva por sala al iniciar la jornada y avisar a esterilización con un caso de anticipación.',
            'etiquetas' => ['recambio', 'esterilización'], 'impacto' => 1_200_000,
        ],
        [
            'autor' => 'pimienta', 'mes' => '2026-06', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'clinica', 'causa' => 'complicacion_clinica', 'cups' => '541100',
            'titulo' => 'Laparotomías de urgencia nocturnas: reservar sangre antes de entrar a sala',
            'contexto' => 'Dos laparotomías exploratorias de la madrugada se complicaron con sangrado mayor y hubo que esperar la reserva de glóbulos rojos con el paciente abierto. Los casos superaron en más de 60 % su costo estándar.',
            'leccion' => 'En abdomen agudo con sospecha de sangrado la reserva de sangre debe pedirse desde urgencias, no desde la sala. La espera prolonga anestesia y sala, y aumenta el riesgo.',
            'recomendacion' => 'Incluir la solicitud de reserva de hemoderivados en la lista de chequeo prequirúrgica de laparotomía de urgencia.',
            'etiquetas' => ['urgencias', 'hemoderivados'], 'impacto' => 1_800_000,
        ],
        [
            'autor' => 'aragon', 'mes' => '2026-06', 'estado' => 'publicada', 'red' => true,
            'tipo' => 'leccion', 'categoria' => 'costos', 'causa' => null, 'cups' => '793500',
            'titulo' => 'El material de osteosíntesis se factura aparte y el costeo lo debe separar',
            'contexto' => 'Las osteosíntesis de fémur, tibia y radio distal aparecen con margen negativo de casi 100 %. El clavo o la placa por sí solos superan la tarifa del procedimiento.',
            'leccion' => 'En la práctica el material de osteosíntesis se factura a la EPS como ítem aparte de la tarifa quirúrgica. Compararlo contra la tarifa del procedimiento hace ver a la ortopedia como el servicio más deficitario cuando no lo es.',
            'recomendacion' => 'Marcar los implantes como insumo facturable aparte y comparar el costo sin implante contra la tarifa, y el implante contra lo facturado por él.',
            'etiquetas' => ['ortopedia', 'facturación'], 'impacto' => null,
        ],
        [
            'autor' => 'gomez', 'mes' => '2026-07', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'protocolo', 'causa' => 'consumo_excesivo_insumos', 'cups' => '470100',
            'titulo' => 'Apendicectomía laparoscópica: abrir trócares desechables solo al confirmar la vía',
            'contexto' => 'En apendicectomías laparoscópicas convertidas a abierta se habían abierto ya los tres trócares desechables y la bolsa de extracción.',
            'leccion' => 'Los trócares son el insumo más caro del protocolo. Abrirlos uno a uno, a medida que se confirma la vía laparoscópica, evita perder el set completo en las conversiones.',
            'recomendacion' => 'Abrir primero la aguja de Veress y el trócar umbilical; el resto, tras la exploración inicial.',
            'etiquetas' => ['laparoscopia', 'insumos'], 'impacto' => 900_000,
        ],
        [
            'autor' => 'bolano', 'mes' => '2026-07', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'seguridad', 'causa' => null, 'cups' => '741000',
            'titulo' => 'Carbetocina solo en riesgo alto de hemorragia posparto',
            'contexto' => 'Algunas cesáreas usaban carbetocina como uterotónico de rutina, con un costo casi veinte veces mayor que la oxitocina.',
            'leccion' => 'La oxitocina sigue siendo de primera línea. La carbetocina se justifica en riesgo alto de hemorragia (macrosomía, polihidramnios, gestación múltiple, atonía previa), y ahí sí evita sobrecostos mayores por transfusión.',
            'recomendacion' => 'Dejar la carbetocina fuera del protocolo base y registrarla como opcional con la indicación.',
            'etiquetas' => ['obstetricia', 'uterotónicos'], 'impacto' => 1_500_000,
        ],
        [
            'autor' => 'admin', 'mes' => '2026-07', 'estado' => 'publicada', 'red' => true,
            'tipo' => 'leccion', 'categoria' => 'proceso', 'causa' => null, 'cups' => null,
            'titulo' => 'Pasar a bolsas CIF mostró que el costo/hora de las salas estaba sobreestimado',
            'contexto' => 'El 1 de julio el hospital activó las bolsas de costos indirectos. La bolsa de infraestructura repartida por minuto de quirófano da alrededor de $25.000 por hora, mientras el costo/hora digitado de las salas iba de $26.000 a $52.000.',
            'leccion' => 'El costo/hora de las salas se había estimado incluyendo equipos y personal de apoyo que también se cobraban por separado. El método por bolsas lo hizo visible.',
            'recomendacion' => 'Antes de activar bolsas, conciliar con contabilidad qué incluye el costo/hora de cada sala.',
            'etiquetas' => ['CIF', 'contabilidad'], 'impacto' => null,
        ],
        [
            'autor' => 'lacouture', 'mes' => '2026-08', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'clinica', 'causa' => 'tiempo_quirurgico_prolongado', 'cups' => '793200',
            'titulo' => 'Bloqueo supraclavicular guiado por ecografía en cirugía de radio distal',
            'contexto' => 'Las osteosíntesis de radio distal se hacían con anestesia general, con inducción y despertar que sumaban 25 minutos de sala.',
            'leccion' => 'Con bloqueo supraclavicular guiado por el ecógrafo portátil la inducción se hace en el área prequirúrgica mientras termina el caso anterior; la sala se ocupa menos y la analgesia posoperatoria es mejor.',
            'recomendacion' => 'Ofrecer bloqueo regional como primera opción en cirugía de antebrazo y mano.',
            'etiquetas' => ['anestesia regional', 'ecografía'], 'impacto' => 700_000,
        ],
        [
            'autor' => 'fince', 'mes' => '2026-08', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'costos', 'causa' => 'consumo_excesivo_insumos', 'cups' => '512200',
            'titulo' => 'Compresas de laparotomía: abrir por paquetes de cinco, no de diez',
            'contexto' => 'En colecistectomías abiertas sin complicación se abrían dos paquetes de compresas de laparotomía y se usaba menos de la mitad.',
            'leccion' => 'Lo que se abre y no se usa se desecha igual. El conteo de compresas se mantiene sin problema con un paquete y otro de reserva cerrado en la mesa auxiliar.',
            'recomendacion' => 'Un paquete abierto por defecto; el segundo solo si se necesita.',
            'etiquetas' => ['insumos', 'cirugía general'], 'impacto' => 400_000,
        ],
        [
            'autor' => 'cuello', 'mes' => '2026-08', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'protocolo', 'causa' => 'equipo_adicional', 'cups' => '602900',
            'titulo' => 'RTU de próstata: verificar el asa de resección antes de la inducción',
            'contexto' => 'En dos resecciones transuretrales el asa disponible no correspondía al resectoscopio y hubo que esperar el cambio con el paciente anestesiado.',
            'leccion' => 'El asa es específica del equipo. La verificación debe hacerse en el alistamiento, no en la mesa.',
            'recomendacion' => 'Incluir la compatibilidad del asa en la lista de chequeo de la torre de endourología.',
            'etiquetas' => ['urología', 'alistamiento'], 'impacto' => 600_000,
        ],
        [
            'autor' => 'mendoza', 'mes' => '2026-09', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'proceso', 'causa' => null, 'cups' => '530300',
            'titulo' => 'Agrupar la cirugía pediátrica en una sola franja de la mañana',
            'contexto' => 'Las herniotomías y amigdalectomías pediátricas quedaban dispersas en la semana, cada una con su preparación de equipo pediátrico.',
            'leccion' => 'Concentrar los casos pediátricos en una franja reduce cambios de material, permite tener al anestesiólogo con experiencia pediátrica y acorta el ayuno de los niños.',
            'recomendacion' => 'Bloque pediátrico fijo los martes y jueves de 7:00 a 11:00 en la Sala 1.',
            'etiquetas' => ['pediatría', 'programación'], 'impacto' => null,
        ],
        [
            'autor' => 'celedon', 'mes' => '2026-09', 'estado' => 'publicada', 'red' => true,
            'tipo' => 'mejor_practica', 'categoria' => 'proceso', 'causa' => null, 'cups' => '134100',
            'titulo' => 'Jornadas de catarata: cinco casos seguidos en la Sala 5',
            'contexto' => 'La facoemulsificación se programaba uno o dos casos por semana, con montaje y desmontaje del microscopio y el facoemulsificador cada vez.',
            'leccion' => 'Las jornadas concentradas aprovechan el montaje y bajan el costo por hora de los equipos de oftalmología, que se usan pocas horas al año.',
            'recomendacion' => 'Programar la catarata en jornadas quincenales de cinco a seis casos.',
            'etiquetas' => ['oftalmología', 'equipos'], 'impacto' => 1_100_000,
        ],
        [
            'autor' => 'admin', 'mes' => '2026-09', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'proceso', 'causa' => null, 'cups' => null,
            'titulo' => 'La Sala 2 está subutilizada mientras la obstétrica supera el 60 %',
            'contexto' => 'Las reglas de sala subutilizada alertaron de forma repetida la Sala 2 (cerca del 20 %) y la Sala 5, mientras la Sala 3 obstétrica pasó del 60 % de su capacidad.',
            'leccion' => 'La programación por especialidad dejó salas vacías y otra saturada. Las ginecológicas programadas pueden operarse en la Sala 2.',
            'recomendacion' => 'Mover histerectomías y quistectomías programadas a la Sala 2 y dejar la Sala 3 para obstetricia y urgencias.',
            'etiquetas' => ['capacidad', 'programación'], 'impacto' => 3_000_000,
        ],
        [
            'autor' => 'toncel', 'mes' => '2026-10', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'seguridad', 'causa' => 'complicacion_clinica', 'cups' => '666200',
            'titulo' => 'Embarazo ectópico roto: calentador de fluidos desde el ingreso',
            'contexto' => 'En las salpingectomías por embarazo ectópico roto con hemoperitoneo se transfundía con líquidos fríos y aparecía hipotermia en el posoperatorio.',
            'leccion' => 'La hipotermia empeora la coagulación y prolonga la recuperación. El calentador de fluidos debe estar en sala antes del ingreso de la paciente.',
            'recomendacion' => 'Incluir el calentador de fluidos en el protocolo del procedimiento.',
            'etiquetas' => ['obstetricia', 'hipotermia'], 'impacto' => null,
        ],
        [
            'autor' => 'daza', 'mes' => '2026-10', 'estado' => 'publicada', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'proceso', 'causa' => null, 'cups' => null,
            'titulo' => 'Registrar resultado clínico y facturación el mismo día del cierre',
            'contexto' => 'La regla de completitud mostró que cerca de un 10 % de las cirugías quedaban sin resultado clínico, y que la facturación de los últimos días del mes se registraba semanas después.',
            'leccion' => 'Sin resultado clínico no se puede medir calidad, y sin factura no se puede medir margen. Se pierde justo la información que los comités piden.',
            'recomendacion' => 'Cerrar el registro de la cirugía con resultado clínico al egreso de recuperación; facturación revisa los pendientes cada viernes.',
            'etiquetas' => ['completitud', 'registro'], 'impacto' => null,
        ],
        // En revisión, borradores y archivo: el ciclo completo.
        [
            'autor' => 'pimienta', 'mes' => '2026-10', 'estado' => 'en_revision', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'costos', 'causa' => 'consumo_excesivo_insumos', 'cups' => '535100',
            'titulo' => 'Malla de 15 x 15 en eventraciones pequeñas',
            'contexto' => 'En eventraciones de defecto menor de 4 cm se usaba la malla de 15 x 15 cm del protocolo.',
            'leccion' => 'Para defectos pequeños la malla de 6 x 11 cm da el mismo solapamiento con la mitad del costo.',
            'recomendacion' => 'Elegir la malla según el tamaño del defecto medido en la cirugía.',
            'etiquetas' => ['hernias', 'mallas'], 'impacto' => 300_000,
        ],
        [
            'autor' => 'aragon', 'mes' => '2026-10', 'estado' => 'en_revision', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'proceso', 'causa' => 'retraso_alistamiento', 'cups' => '793600',
            'titulo' => 'El arco en C se comparte con urgencias y retrasa la Sala 4',
            'contexto' => 'Varias osteosíntesis de tibia esperaron el arco en C, que estaba en urgencias para una reducción.',
            'leccion' => 'Con un solo arco en C, la programación de ortopedia debe coordinarse con las reducciones de urgencias.',
            'recomendacion' => 'Hacer las reducciones cerradas programables en la tarde y dejar el arco en la Sala 4 por la mañana.',
            'etiquetas' => ['ortopedia', 'equipos'], 'impacto' => 500_000,
        ],
        [
            'autor' => 'gomez', 'mes' => '2026-10', 'estado' => 'en_revision', 'red' => false,
            'tipo' => 'mejor_practica', 'categoria' => 'protocolo', 'causa' => null, 'cups' => '283000',
            'titulo' => 'Mesa de amigdalectomía preparada por defecto',
            'contexto' => 'El instrumental de amigdalectomía se armaba caso a caso.',
            'leccion' => 'Una caja estandarizada de amigdalectomía reduce el alistamiento y los faltantes.',
            'recomendacion' => 'Crear la caja con esterilización.',
            'etiquetas' => ['otorrino', 'instrumental'], 'impacto' => null,
        ],
        [
            'autor' => 'bolano', 'mes' => '2026-10', 'estado' => 'borrador', 'red' => false, 'devuelta' => 'Agregue cuántos casos se revisaron y el periodo; sin eso no se puede validar.',
            'tipo' => 'leccion', 'categoria' => 'clinica', 'causa' => null, 'cups' => '690200',
            'titulo' => 'AMEU en lugar de legrado en abortos incompletos tempranos',
            'contexto' => 'Los legrados obstétricos se hacen en la Sala 3 con sedación.',
            'leccion' => 'La aspiración manual endouterina en la Sala 5 libera la sala obstétrica y es igual de efectiva en gestaciones tempranas.',
            'recomendacion' => null,
            'etiquetas' => ['obstetricia'], 'impacto' => null,
        ],
        [
            'autor' => 'freyle', 'mes' => '2026-10', 'estado' => 'borrador', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'costos', 'causa' => null, 'cups' => null,
            'titulo' => 'Sugammadex solo con bloqueo profundo residual',
            'contexto' => 'Se está usando sugammadex como reversor de rutina en algunos casos.',
            'leccion' => 'La neostigmina con atropina es suficiente en la mayoría; el sugammadex se reserva para bloqueo profundo o vía aérea difícil.',
            'recomendacion' => null,
            'etiquetas' => ['anestesia'], 'impacto' => null,
        ],
        [
            'autor' => 'admin', 'mes' => '2026-06', 'estado' => 'archivada', 'red' => false,
            'tipo' => 'leccion', 'categoria' => 'proceso', 'causa' => null, 'cups' => null,
            'titulo' => 'Formato en papel para tiempos de sala (reemplazado por el registro digital)',
            'contexto' => 'Antes del aplicativo los tiempos se anotaban en papel y se transcribían.',
            'leccion' => 'La transcripción generaba errores de hora. Superada por el registro directo.',
            'recomendacion' => null,
            'etiquetas' => ['registro'], 'impacto' => null,
        ],
    ],

    // Lecciones del otro hospital compartidas en la red.
    'red' => [
        [
            'tipo' => 'mejor_practica', 'categoria' => 'costos', 'mes' => '2026-08', 'cups' => '740001', 'especialidad' => 'Ginecobstetricia',
            'titulo' => 'Kit de cesárea armado por esterilización',
            'contexto' => 'Cada cesárea se alistaba con insumos sueltos de la farmacia de cirugía.',
            'leccion' => 'Un kit sellado con lo que se usa siempre evita sobrantes abiertos y faltantes; el costo por cesárea bajó y se volvió predecible.',
            'recomendacion' => 'Acordar el contenido del kit con ginecología y anestesia y revisarlo cada semestre.',
        ],
        [
            'tipo' => 'leccion', 'categoria' => 'proceso', 'mes' => '2026-09', 'cups' => null, 'especialidad' => 'Cirugía general',
            'titulo' => 'Llamar al siguiente paciente cuando inicia el cierre',
            'contexto' => 'El recambio entre cirugías superaba los 40 minutos porque el siguiente paciente se llamaba al terminar el caso.',
            'leccion' => 'Avisar al iniciar el cierre permite que el paciente llegue preparado mientras se hace el aseo; el recambio bajó a menos de 25 minutos.',
            'recomendacion' => 'Incluir el aviso en el rol de la circulante.',
        ],
    ],

    'hilos' => [
        [
            'comunidad' => 'Ginecobstetricia', 'autor' => 'toncel', 'enlace' => ['procedimiento', '740001'], 'resuelto' => true, 'leccion' => 0,
            'titulo' => '¿Por qué la cesárea base sale siempre en sobrecosto?',
            'cuerpo' => 'Revisando el tablero, la cesárea segmentaria aparece con más de 100 casos en sobrecosto alto. Me parece que el problema es el estándar de 120 minutos y no lo que hacemos. ¿Alguien tiene la mediana real?',
            'respuestas' => [
                ['bolano', 'En mis programadas no paso de 80 minutos de sala. Las urgentes de la noche sí se alargan.'],
                ['freyle', 'Desde anestesia: con raquídea la inducción toma 10 a 12 minutos. 120 minutos no corresponden a la práctica actual.'],
                ['admin', 'La mediana del semestre es de 75 a 80 minutos. Propongo ajustar el protocolo y lo dejamos como lección.', true],
            ],
        ],
        [
            'comunidad' => 'Comunidad general de cirugía', 'autor' => 'freyle', 'enlace' => null, 'resuelto' => true, 'leccion' => 1,
            'titulo' => 'Consumo de sevoflurano por sala',
            'cuerpo' => 'Las salas 1 y 2 gastan casi el doble de sevoflurano por hora que la Sala 4. Antes de concluir algo, ¿qué flujos de gas fresco están usando?',
            'respuestas' => [
                ['lacouture', 'En la Sala 4 bajamos a 1 L/min después de la inducción desde hace meses.'],
                ['admin', 'Llevemos esto a protocolo: anestesia de bajo flujo como estándar.', true],
            ],
        ],
        [
            'comunidad' => 'Comunidad general de cirugía', 'autor' => 'daza', 'enlace' => null, 'resuelto' => false, 'leccion' => null,
            'titulo' => 'Retrasos de la primera cirugía de la mañana',
            'cuerpo' => 'La primera programada de la Sala 1 rara vez empieza a las 7:00. Pacientes que llegan tarde al prequirúrgico y consentimientos sin firmar. ¿Cómo lo manejan en otras salas?',
            'respuestas' => [
                ['pimienta', 'En cirugía general citamos a las 6:00 y la enfermera revisa consentimientos el día anterior.'],
                ['gomez', 'También ayuda dejar la mesa de Mayo armada desde la noche para el primer caso.'],
            ],
        ],
        [
            'comunidad' => 'Ortopedia y traumatología', 'autor' => 'aragon', 'enlace' => ['procedimiento', '793500'], 'resuelto' => false, 'leccion' => null,
            'titulo' => 'Margen negativo en osteosíntesis: ¿lo llevamos a facturación?',
            'cuerpo' => 'El sistema nos muestra pérdidas de casi el 100 % en fémur y tibia. El clavo está dentro del protocolo, pero se factura aparte. ¿Cómo lo separamos?',
            'respuestas' => [
                ['admin', 'Lo planteamos al desarrollador: hay que marcar los implantes como insumo facturable aparte. Mientras tanto la lección queda publicada.'],
            ],
        ],
        [
            'comunidad' => 'Urología', 'autor' => 'cuello', 'enlace' => null, 'resuelto' => true, 'leccion' => null,
            'titulo' => 'Lista de chequeo de la torre de endourología',
            'cuerpo' => 'Propongo una lista de chequeo para la torre: óptica, fuente de luz, asa compatible, glicina suficiente.',
            'respuestas' => [
                ['gomez', 'Agregaría la verificación del cable del resectoscopio, que también ha fallado.', true],
            ],
        ],
        [
            'comunidad' => 'Oftalmología', 'autor' => 'celedon', 'enlace' => ['procedimiento', '134100'], 'resuelto' => false, 'leccion' => null,
            'titulo' => 'Jornadas de catarata: ¿quincenales o mensuales?',
            'cuerpo' => 'Con cinco a seis casos por jornada el costo/hora del facoemulsificador se reparte mejor. ¿Alguna restricción de anestesia para jornadas largas?',
            'respuestas' => [
                ['lacouture', 'Con sedación ligera no hay problema; solo hay que prever la recuperación en paralelo.'],
            ],
        ],
    ],
];
