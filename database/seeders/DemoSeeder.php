<?php

namespace Database\Seeders;

use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ResultadoClinico;
use App\Services\Costing\TdabcCostingService;
use App\Services\Indicators\KpiService;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Movimiento de ejemplo para revisión: cirugías, costeo, facturación y
 * resultados clínicos montados sobre el catálogo de CatalogoQuirurgicoSeeder.
 *
 * TODOS los registros son ficticios y están marcados con [SEMILLA]. No
 * corresponden a hospitales ni pacientes reales.
 *
 * Incluye la cirugía de referencia del caso de prueba TDABC: una cesárea
 * cuyo costo total debe dar exactamente $520.000 COP.
 *
 * NO ejecutar en producción: genera datos falsos que contaminan los
 * indicadores. Para una instalación real usa solo HospitalSeeder,
 * UsuarioSeeder y CatalogoQuirurgicoSeeder.
 *
 * @phpstan-import-type CatalogoSembrado from CatalogoQuirurgicoSeeder
 */
class DemoSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * Cirugías aleatorias por procedimiento (código CUPS => cantidad).
     *
     * @var array<string, array<array-key, int>>
     */
    protected const VOLUMEN = [
        '800100200-1' => ['740001' => 9, '780201' => 10, '741500' => 10, '744201' => 8],
        '800300400-2' => ['740001' => 6],
    ];

    public function run(): void
    {
        $motor = app(TdabcCostingService::class);
        $catalogoSeeder = new CatalogoQuirurgicoSeeder;

        if ($this->consola !== null) {
            $catalogoSeeder->setCommand($this->consola);
        }

        $this->call([
            HospitalSeeder::class,
            UsuarioSeeder::class,
        ]);

        foreach (CatalogoQuirurgicoSeeder::CATALOGO_POR_NIT as $nit => $perfil) {
            $hospital = Hospital::query()->where('nit', $nit)->firstOrFail();
            $catalogo = $catalogoSeeder->sembrar($hospital, $perfil);

            HospitalContext::set($hospital->id);

            try {
                if ($perfil === 'principal') {
                    $this->sembrarCesareaDeReferencia($hospital, $catalogo, $motor);
                }

                $this->sembrarMovimiento($hospital, $catalogo, $motor, self::VOLUMEN[$nit]);
            } finally {
                HospitalContext::clear();
            }
        }
    }

    /**
     * Cirugía de referencia del caso de prueba TDABC — costo total $520.000:
     *
     *   cirujano       $50.000/h × 1,5 h =  75.000
     *   ayudante       $30.000/h × 1,5 h =  45.000
     *   anestesiólogo  $50.000/h × 2 h   = 100.000
     *   instrumentador $20.000/h × 2 h   =  40.000
     *   circulante     $15.000/h × 2 h   =  30.000
     *   sala           $40.000/h × 2 h   =  80.000
     *   insumos                          = 150.000
     *   ──────────────────────────────────────────
     *   TOTAL                              520.000
     *
     * @param  CatalogoSembrado  $catalogo
     */
    protected function sembrarCesareaDeReferencia(
        Hospital $hospital,
        array $catalogo,
        TdabcCostingService $motor,
    ): void {
        $paciente = Paciente::factory()->create([
            'hospital_id' => $hospital->id,
            'nombres' => 'María José',
            'apellidos' => 'Uriana Gámez',
            'sexo' => 'F',
        ]);

        $cesarea = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $catalogo['salas'][0]->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
            'tipo' => 'programada',
            'estado' => 'realizada',
            'diagnostico_cie10' => 'O82',
            'observaciones' => 'Cirugía de referencia del caso de prueba TDABC: costo total $520.000 [SEMILLA]',
        ]);
        $cesarea->procedimientos()->attach($catalogo['procedimientos']['740001']->id, ['es_principal' => true]);

        $participaciones = [
            ['cirujano', 90],
            ['ayudante', 90],
            ['anestesiologo', 120],
            ['instrumentador', 120],
            ['circulante', 120],
        ];
        foreach ($participaciones as [$rol, $minutos]) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cesarea->id,
                'recurso_humano_id' => $catalogo['personal'][$rol][0]->id,
                'rol' => $rol,
                'minutos_participacion' => $minutos,
            ]);
        }

        $consumos = [
            ['MED-001', 2],   //  10.000
            ['MED-002', 1],   //  25.000
            ['MAT-001', 3],   //  45.000
            ['MAT-002', 10],  //  20.000
            ['MAT-003', 6],   //  18.000
            ['MAT-004', 1],   //  32.000
        ];
        foreach ($consumos as [$codigo, $cantidad]) {
            $this->registrarConsumo($cesarea, $catalogo['insumos'][$codigo], $cantidad);
        }

        $motor->calcular($cesarea);

        Facturacion::create([
            'cirugia_id' => $cesarea->id,
            'hospital_id' => $hospital->id,
            'valor_facturado' => 637_500, // SOAT 850.000 − 25 %
            'valor_glosado' => 0,
            'valor_recaudado' => 637_500,
            'tarifa_referencia_soat' => 850_000,
            'fecha_facturacion' => '2026-06-15',
        ]);

        ResultadoClinico::create([
            'cirugia_id' => $cesarea->id,
            'hospital_id' => $hospital->id,
            'dias_estancia' => 3,
        ]);
    }

    /**
     * Cirugías aleatorias para poblar los dashboards.
     *
     * @param  CatalogoSembrado  $catalogo
     * @param  array<array-key, int>  $volumen
     */
    protected function sembrarMovimiento(
        Hospital $hospital,
        array $catalogo,
        TdabcCostingService $motor,
        array $volumen,
    ): void {
        foreach ($volumen as $clave => $cantidad) {
            // PHP convierte a int las claves que son cadenas numéricas, y los
            // códigos CUPS lo son; hay que devolverlas a string para poder
            // compararlas.
            $cups = (string) $clave;

            for ($i = 0; $i < $cantidad; $i++) {
                // Dos colecistectomías con consumo desbordado: outliers
                // deliberados para que se disparen las alertas de sobrecosto.
                $esOutlier = $cups === '741500' && $i < 2;

                $this->crearCirugiaAleatoria($hospital, $motor, $catalogo, $cups, $esOutlier);
            }
        }
    }

    /**
     * @param  CatalogoSembrado  $catalogo
     */
    protected function crearCirugiaAleatoria(
        Hospital $hospital,
        TdabcCostingService $motor,
        array $catalogo,
        string $cups,
        bool $esOutlier,
    ): void {
        $protocolo = $catalogo['protocolos'][$cups];
        $procedimiento = $catalogo['procedimientos'][$cups];
        $salas = $catalogo['salas'];

        $inicio = Carbon::now()
            ->subDays(random_int(7, 90))
            ->setTime(random_int(7, 16), [0, 30][random_int(0, 1)], 0);
        $duracion = (int) round($protocolo['duracion'] * random_int(80, 130) / 100);

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);

        $cirugia = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $salas[array_rand($salas)]->id,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fin' => $inicio->copy()->addMinutes($duracion),
            'tipo' => random_int(1, 100) <= 70 ? 'programada' : 'urgencia',
            'estado' => 'realizada',
            'diagnostico_cie10' => null,
        ]);
        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        // Equipo quirúrgico: cirujano y ayudante ~75 % de la duración,
        // el resto de roles acompañan toda la cirugía.
        foreach (RolQuirurgico::values() as $rol) {
            $pool = $catalogo['personal'][$rol];
            $minutosBase = in_array($rol, ['cirujano', 'ayudante'], true)
                ? (int) round($duracion * 0.75)
                : $duracion;

            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $pool[array_rand($pool)]->id,
                'rol' => $rol,
                'minutos_participacion' => max(15, (int) round($minutosBase * random_int(90, 110) / 100)),
            ]);
        }

        foreach ($protocolo['equipos'] as $codigo) {
            $cirugia->equiposMedicos()->attach($catalogo['equipos'][$codigo]->id, [
                'minutos_uso' => max(15, (int) round($duracion * random_int(60, 100) / 100)),
            ]);
        }

        // Consumo de insumos (los outliers multiplican las cantidades ×5)
        $factorOutlier = $esOutlier ? 5 : 1;
        $seleccion = collect($protocolo['insumos'])
            ->shuffle()
            ->take(random_int(3, count($protocolo['insumos'])));

        foreach ($seleccion as $codigo) {
            $this->registrarConsumo($cirugia, $catalogo['insumos'][$codigo], random_int(1, 6) * $factorOutlier);
        }

        // 10 % de las cirugías quedan sin costear (KPI de completitud < 100 %)
        $costeada = random_int(1, 100) <= 90;
        if ($costeada) {
            $motor->calcular($cirugia);
        }

        // 85 % facturadas
        if ($costeada && random_int(1, 100) <= 85) {
            $tarifaSoat = (float) $procedimiento->tarifa_soat;
            $facturado = round($tarifaSoat * KpiService::FACTOR_REFERENCIA_SOAT * random_int(95, 115) / 100, 2);
            $glosado = random_int(1, 100) <= 15 ? round($facturado * random_int(5, 20) / 100, 2) : 0.0;
            $recaudado = round(($facturado - $glosado) * random_int(80, 100) / 100, 2);

            Facturacion::create([
                'cirugia_id' => $cirugia->id,
                'hospital_id' => $hospital->id,
                'valor_facturado' => $facturado,
                'valor_glosado' => $glosado,
                'valor_recaudado' => $recaudado,
                'tarifa_referencia_soat' => $tarifaSoat,
                'fecha_facturacion' => $inicio->copy()->addDays(random_int(3, 15))->toDateString(),
            ]);
        }

        // 80 % con resultado clínico registrado
        if (random_int(1, 100) <= 80) {
            ResultadoClinico::factory()->create([
                'cirugia_id' => $cirugia->id,
                'hospital_id' => $hospital->id,
            ]);
        }
    }

    protected function registrarConsumo(Cirugia $cirugia, Insumo $insumo, int $cantidad): void
    {
        ConsumoInsumo::create([
            'cirugia_id' => $cirugia->id,
            'insumo_id' => $insumo->id,
            'cantidad' => $cantidad,
            'costo_unitario_registrado' => $insumo->costo_unitario,
            'costo_total' => round($cantidad * (float) $insumo->costo_unitario, 2),
        ]);
    }
}
