<?php

namespace Database\Seeders;

use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Hospital;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Módulo de registro: las cirugías realizadas de cada hospital.
 *
 * No recibe el catálogo por parámetro, lo lee de la base: los insumos y
 * equipos de cada cirugía salen de la plantilla del procedimiento, que es
 * exactamente como los propone la aplicación cuando un digitador abre el
 * formulario. Así el dato sembrado se parece al dato real.
 *
 * Cada cirugía queda atribuida a un digitador del hospital (`registrado_por`),
 * que es lo que hace útil la trazabilidad del explorador.
 *
 * No cuesta nada: de eso se encarga CosteoSeeder. Requiere HospitalSeeder,
 * UsuarioSeeder, CatalogoQuirurgicoSeeder y PacienteSeeder.
 *
 * OJO — a diferencia de los seeders de catálogo, este NO es idempotente: una
 * cirugía no tiene clave natural con la que reconocerla, así que cada corrida
 * añade `VOLUMEN` cirugías nuevas. Es lo correcto para movimiento (dos
 * apendicectomías iguales el mismo día son dos cirugías, no una repetida),
 * pero significa que ejecutarlo tres veces triplica el volumen.
 */
class CirugiaSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * Cirugías por hospital y procedimiento (NIT => código CUPS => cantidad).
     *
     * @var array<string, array<array-key, int>>
     */
    public const VOLUMEN = [
        '800100200-1' => ['740001' => 9, '780201' => 10, '741500' => 10, '744201' => 8],
        '800300400-2' => ['740001' => 6],
    ];

    /**
     * Procedimientos con casos de consumo desbordado, y cuántos.
     *
     * Son outliers deliberados: sin ellos la bandeja de alertas de sobrecosto
     * nace vacía y no hay forma de mostrar cómo funciona.
     *
     * @var array<array-key, int>
     */
    public const OUTLIERS = ['741500' => 2];

    public function run(): void
    {
        foreach (self::VOLUMEN as $nit => $volumen) {
            $hospital = Hospital::query()->where('nit', $nit)->first();

            if ($hospital === null) {
                $this->advertir("Sin hospital con NIT {$nit}: se omiten sus cirugías. Ejecuta HospitalSeeder primero.");

                continue;
            }

            $creadas = $this->sembrar($hospital, $volumen);
            $this->informar("Cirugías registradas en {$hospital->nombre}: {$creadas}.");
        }
    }

    /**
     * Registra las cirugías de un hospital y devuelve cuántas creó.
     *
     * @param  array<array-key, int>  $volumen
     */
    public function sembrar(Hospital $hospital, array $volumen): int
    {
        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            $contexto = $this->contexto($hospital);

            if ($contexto === null) {
                return 0;
            }

            $creadas = 0;

            foreach ($volumen as $clave => $cantidad) {
                // PHP convierte a int las claves que son cadenas numéricas, y
                // los códigos CUPS lo son; hay que devolverlas a string.
                $cups = (string) $clave;
                $procedimiento = $contexto['procedimientos'][$cups] ?? null;

                if ($procedimiento === null) {
                    $this->advertir("{$hospital->nombre} no tiene el procedimiento {$cups}: se omite.");

                    continue;
                }

                $outliers = self::OUTLIERS[$cups] ?? 0;

                for ($i = 0; $i < $cantidad; $i++) {
                    $this->registrarCirugia($hospital, $procedimiento, $contexto, $i < $outliers);
                    $creadas++;
                }
            }

            return $creadas;
        } finally {
            HospitalContext::set($anterior);
        }
    }

    /**
     * Todo lo que hace falta del hospital para registrar cirugías. Devuelve
     * null si le falta algo, en vez de reventar a mitad de la siembra.
     *
     * @return array{
     *     salas: Collection<int, SalaOperatoria>,
     *     personal: array<string, Collection<int, RecursoHumano>>,
     *     procedimientos: array<array-key, ProcedimientoQuirurgico>,
     *     pacientes: Collection<int, Paciente>,
     *     digitadores: Collection<int, User>
     * }|null
     */
    protected function contexto(Hospital $hospital): ?array
    {
        $salas = SalaOperatoria::query()->get();
        $pacientes = Paciente::query()->get();
        $personal = RecursoHumano::query()->get()->groupBy('rol')->all();

        $faltantes = [];

        if ($salas->isEmpty()) {
            $faltantes[] = 'salas';
        }

        if ($pacientes->isEmpty()) {
            $faltantes[] = 'pacientes';
        }

        foreach (RolQuirurgico::values() as $rol) {
            if (! isset($personal[$rol]) || $personal[$rol]->isEmpty()) {
                $faltantes[] = "personal ({$rol})";
            }
        }

        if ($faltantes !== []) {
            $this->advertir(
                "{$hospital->nombre} no tiene ".implode(', ', $faltantes).
                ': se omiten sus cirugías. Ejecuta CatalogoQuirurgicoSeeder y PacienteSeeder primero.',
            );

            return null;
        }

        $procedimientos = ProcedimientoQuirurgico::query()
            ->with(['plantillaInsumos.insumo', 'plantillaEquipos'])
            ->get()
            ->keyBy('codigo_cups')
            ->all();

        return [
            'salas' => $salas,
            'personal' => $personal,
            'procedimientos' => $procedimientos,
            'pacientes' => $pacientes,
            'digitadores' => User::query()
                ->where('hospital_id', $hospital->id)
                ->where('role', 'digitador')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    protected function registrarCirugia(
        Hospital $hospital,
        ProcedimientoQuirurgico $procedimiento,
        array $contexto,
        bool $esOutlier,
    ): void {
        $duracionBase = $procedimiento->duracion_estimada_minutos ?? 90;
        $duracion = (int) round($duracionBase * random_int(80, 130) / 100);

        $inicio = Carbon::now()
            ->subDays(random_int(7, 90))
            ->setTime(random_int(7, 16), [0, 30][random_int(0, 1)], 0);

        /** @var Collection<int, User> $digitadores */
        $digitadores = $contexto['digitadores'];

        $cirugia = Cirugia::create([
            'hospital_id' => $hospital->id,
            'registrado_por' => $digitadores->isEmpty() ? null : $digitadores->random()->id,
            'paciente_id' => $contexto['pacientes']->random()->id,
            'sala_operatoria_id' => $contexto['salas']->random()->id,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fin' => $inicio->copy()->addMinutes($duracion),
            'tipo' => random_int(1, 100) <= 70 ? 'programada' : 'urgencia',
            'estado' => 'realizada',
            'diagnostico_cie10' => null,
        ]);

        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        $this->asignarEquipoQuirurgico($cirugia, $contexto['personal'], $duracion);
        $this->registrarEquiposMedicos($cirugia, $procedimiento, $duracion);
        $this->registrarConsumos($cirugia, $procedimiento, $esOutlier);
    }

    /**
     * Cirujano y ayudante entran ~75 % de la duración; el resto del equipo
     * acompaña toda la cirugía.
     *
     * @param  array<string, Collection<int, RecursoHumano>>  $personal
     */
    protected function asignarEquipoQuirurgico(Cirugia $cirugia, array $personal, int $duracion): void
    {
        foreach (RolQuirurgico::values() as $rol) {
            $minutosBase = in_array($rol, ['cirujano', 'ayudante'], true)
                ? (int) round($duracion * 0.75)
                : $duracion;

            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $personal[$rol]->random()->id,
                'rol' => $rol,
                'minutos_participacion' => max(15, (int) round($minutosBase * random_int(90, 110) / 100)),
            ]);
        }
    }

    protected function registrarEquiposMedicos(
        Cirugia $cirugia,
        ProcedimientoQuirurgico $procedimiento,
        int $duracion,
    ): void {
        foreach ($procedimiento->plantillaEquipos as $plantilla) {
            $cirugia->equiposMedicos()->attach($plantilla->equipo_medico_id, [
                'minutos_uso' => max(15, (int) round($duracion * random_int(60, 100) / 100)),
            ]);
        }
    }

    /**
     * Se consume un subconjunto de la plantilla: en la práctica casi nunca se
     * gasta exactamente lo previsto. Los outliers multiplican por cinco.
     */
    protected function registrarConsumos(
        Cirugia $cirugia,
        ProcedimientoQuirurgico $procedimiento,
        bool $esOutlier,
    ): void {
        $plantilla = $procedimiento->plantillaInsumos;

        if ($plantilla->isEmpty()) {
            return;
        }

        $factor = $esOutlier ? 5 : 1;
        $seleccion = $plantilla->shuffle()->take(random_int(3, $plantilla->count()));

        foreach ($seleccion as $linea) {
            $insumo = $linea->insumo;

            if ($insumo === null) {
                continue;
            }

            $cantidad = random_int(1, 6) * $factor;

            ConsumoInsumo::create([
                'cirugia_id' => $cirugia->id,
                'insumo_id' => $insumo->id,
                'fase' => $linea->fase,
                'cantidad' => $cantidad,
                'costo_unitario_registrado' => $insumo->costo_unitario,
                'costo_total' => round($cantidad * (float) $insumo->costo_unitario, 2),
            ]);
        }
    }
}
