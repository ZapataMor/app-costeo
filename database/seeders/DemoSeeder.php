<?php

namespace Database\Seeders;

use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\ResultadoClinico;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Costing\DetectorSobrecostos;
use App\Services\Costing\TdabcCostingService;
use App\Services\Indicators\KpiService;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;

/**
 * Instalación de demostración completa: encadena todos los módulos y les
 * añade lo que solo tiene sentido en una demo.
 *
 *   HospitalSeeder → UsuarioSeeder → CatalogoQuirurgicoSeeder
 *   → PacienteSeeder → CirugiaSeeder → CosteoSeeder
 *   → facturación, resultados clínicos y la cesárea de referencia
 *
 * TODOS los registros son ficticios y están marcados con [SEMILLA]. No
 * corresponden a hospitales ni pacientes reales.
 *
 * NO ejecutar en producción: genera datos falsos que contaminan los
 * indicadores. Para una instalación real, DatabaseSeeder ya se encarga de
 * saltarse este seeder y sembrar solo lo estructural.
 */
class DemoSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * Cirugías que se registran DESPUÉS del costeo y por tanto quedan sin
     * costo, para que el KPI de completitud no dé un 100 % irreal.
     */
    protected const SIN_COSTEAR_POR_HOSPITAL = 2;

    public function run(TdabcCostingService $motor, DetectorSobrecostos $detector): void
    {
        $this->call([
            HospitalSeeder::class,
            UsuarioSeeder::class,
            CatalogoQuirurgicoSeeder::class,
            PacienteSeeder::class,
            CirugiaSeeder::class,
        ]);

        // La cesárea de referencia debe existir antes del costeo para que el
        // motor la calcule igual que a las demás.
        $this->sembrarCesareaDeReferencia($motor);

        $this->call(CosteoSeeder::class);

        $this->sembrarFacturacionYResultados();
        $this->sembrarCirugiasSinCostear();
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
     * Va con participaciones e insumos escritos a mano, no con los aleatorios
     * de CirugiaSeeder: es el caso que fija el resultado esperado del motor.
     */
    protected function sembrarCesareaDeReferencia(TdabcCostingService $motor): void
    {
        $hospital = Hospital::query()->where('nit', '800100200-1')->first();

        if ($hospital === null) {
            return;
        }

        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            $sala = SalaOperatoria::query()->where('nombre', 'Sala 1')->firstOrFail();
            $procedimiento = ProcedimientoQuirurgico::query()->where('codigo_cups', '740001')->firstOrFail();

            $paciente = Paciente::factory()->create([
                'hospital_id' => $hospital->id,
                'nombres' => 'María José',
                'apellidos' => 'Uriana Gámez [SEMILLA]',
                'sexo' => 'F',
            ]);

            $cesarea = Cirugia::create([
                'hospital_id' => $hospital->id,
                'registrado_por' => User::query()
                    ->where('hospital_id', $hospital->id)
                    ->where('role', 'digitador')
                    ->value('id'),
                'paciente_id' => $paciente->id,
                'sala_operatoria_id' => $sala->id,
                'fecha' => '2026-06-10',
                'hora_inicio' => '2026-06-10 08:00:00',
                'hora_fin' => '2026-06-10 10:00:00',
                'tipo' => 'programada',
                'estado' => 'realizada',
                'diagnostico_cie10' => 'O82',
                'observaciones' => 'Cirugía de referencia del caso de prueba TDABC: costo total $520.000 [SEMILLA]',
            ]);
            $cesarea->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

            $participaciones = [
                ['cirujano', 'Dra. Carmen Epiayú [SEMILLA]', 90],
                ['ayudante', 'Dr. Luis Pushaina [SEMILLA]', 90],
                ['anestesiologo', 'Dr. Jorge Ipuana [SEMILLA]', 120],
                ['instrumentador', 'Inst. Kelly Mengual [SEMILLA]', 120],
                ['circulante', 'Aux. Yolima Arpushana [SEMILLA]', 120],
            ];
            foreach ($participaciones as [$rol, $nombre, $minutos]) {
                MiembroEquipoQuirurgico::create([
                    'cirugia_id' => $cesarea->id,
                    'recurso_humano_id' => RecursoHumano::query()->where('nombre', $nombre)->firstOrFail()->id,
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
                $insumo = Insumo::query()->where('codigo', $codigo)->firstOrFail();

                ConsumoInsumo::create([
                    'cirugia_id' => $cesarea->id,
                    'insumo_id' => $insumo->id,
                    'cantidad' => $cantidad,
                    'costo_unitario_registrado' => $insumo->costo_unitario,
                    'costo_total' => round($cantidad * (float) $insumo->costo_unitario, 2),
                ]);
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
        } finally {
            HospitalContext::set($anterior);
        }
    }

    /**
     * Factura el 85 % de lo costeado y registra resultado clínico en el 80 %.
     * Las brechas son deliberadas: un hospital real nunca tiene el 100 %, y
     * los KPI de facturación y seguimiento deben poder mostrarlo.
     */
    protected function sembrarFacturacionYResultados(): void
    {
        foreach (Hospital::all() as $hospital) {
            $anterior = HospitalContext::id();
            HospitalContext::set($hospital->id);

            try {
                $cirugias = Cirugia::query()
                    ->has('costo')
                    ->doesntHave('facturacion')
                    ->with('procedimientos')
                    ->get();

                foreach ($cirugias as $cirugia) {
                    if (random_int(1, 100) <= 85) {
                        $this->facturar($hospital, $cirugia);
                    }

                    if (random_int(1, 100) <= 80 && ! $cirugia->resultadoClinico()->exists()) {
                        ResultadoClinico::factory()->create([
                            'cirugia_id' => $cirugia->id,
                            'hospital_id' => $hospital->id,
                        ]);
                    }
                }
            } finally {
                HospitalContext::set($anterior);
            }
        }
    }

    protected function facturar(Hospital $hospital, Cirugia $cirugia): void
    {
        $procedimiento = $cirugia->procedimientoPrincipal();

        if ($procedimiento === null || $procedimiento->tarifa_soat === null) {
            return;
        }

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
            'fecha_facturacion' => $cirugia->fecha->copy()->addDays(random_int(3, 15))->toDateString(),
        ]);
    }

    /**
     * Cirugías registradas después del costeo: quedan pendientes de costear,
     * que es el estado normal de lo que se operó ayer.
     */
    protected function sembrarCirugiasSinCostear(): void
    {
        $seeder = new CirugiaSeeder;

        if ($this->consola !== null) {
            $seeder->setCommand($this->consola);
        }

        foreach (CirugiaSeeder::VOLUMEN as $nit => $volumen) {
            $hospital = Hospital::query()->where('nit', $nit)->first();

            if ($hospital === null) {
                continue;
            }

            // Un solo procedimiento, el primero del hospital, para no inflar
            // el volumen: lo único que hace falta es que existan.
            $cups = (string) array_key_first($volumen);
            $seeder->sembrar($hospital, [$cups => self::SIN_COSTEAR_POR_HOSPITAL]);
        }
    }
}
