<?php

namespace Database\Seeders;

use App\Enums\EstadoCirugia;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Scopes\HospitalScope;
use App\Services\Costing\DetectorSobrecostos;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;

/**
 * Módulo de costeo: corre el motor TDABC sobre las cirugías realizadas que
 * todavía no tienen costo, y levanta las alertas de sobrecosto que resulten.
 *
 * No inventa cifras — usa el mismo TdabcCostingService que la aplicación, así
 * que lo sembrado es exactamente lo que habría calculado el sistema. Por eso
 * también sirve de comando de recuperación: si un hospital carga su histórico
 * de cirugías por fuera, esto lo costea todo de una pasada.
 *
 * Idempotente: solo toca cirugías sin registro de costo, y el detector no
 * duplica alertas ni pisa las ya revisadas.
 *
 * Requiere CirugiaSeeder (o cirugías cargadas por otra vía).
 */
class CosteoSeeder extends Seeder
{
    use InformaEnConsola;

    public function run(TdabcCostingService $motor, DetectorSobrecostos $detector): void
    {
        foreach (Hospital::all() as $hospital) {
            $resultado = $this->costear($hospital, $motor, $detector);

            $this->informar(sprintf(
                '%s: %d cirugías costeadas, %d alertas de sobrecosto.',
                $hospital->nombre,
                $resultado['costeadas'],
                $resultado['alertas'],
            ));
        }
    }

    /**
     * Costea las cirugías pendientes de un hospital.
     *
     * @return array{costeadas: int, alertas: int}
     */
    public function costear(
        Hospital $hospital,
        TdabcCostingService $motor,
        DetectorSobrecostos $detector,
    ): array {
        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        $costeadas = 0;

        try {
            // Por lotes: un hospital con años de historia sin costear no cabe
            // en memoria de una sola consulta. `chunkById` avanza por id, así
            // que costear dentro del bucle no descoloca la paginación aunque
            // las filas dejen de cumplir el `whereDoesntHave`.
            Cirugia::query()
                ->where('estado', EstadoCirugia::Realizada->value)
                ->whereDoesntHave('costo')
                ->chunkById(100, function ($cirugias) use ($motor, &$costeadas): void {
                    foreach ($cirugias as $cirugia) {
                        $motor->calcular($cirugia);
                        $costeadas++;
                    }
                });

            // El detector se evalúa aparte, y no al vuelo de cada costeo,
            // porque necesita un baseline de casos comparables: las primeras
            // cirugías de cada procedimiento se juzgarían cuando todavía no
            // hay con qué compararlas. Una pasada sobre el histórico ya
            // completo es lo que llena la bandeja.
            $this->repasarHistorico($hospital, $detector);

            $alertas = AlertaSobrecosto::query()
                ->withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospital->id)
                ->count();
        } finally {
            HospitalContext::set($anterior);
        }

        return ['costeadas' => $costeadas, 'alertas' => $alertas];
    }

    /**
     * Pasa el detector por todo lo costeado del hospital.
     *
     * No se cuentan los retornos de `evaluar()`: devuelve también la alerta
     * que ya existía, así que sumarlos contaría dos veces lo mismo. El total
     * se lee de la tabla al final.
     */
    protected function repasarHistorico(Hospital $hospital, DetectorSobrecostos $detector): void
    {
        CostoCirugia::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->with('cirugia.procedimientos')
            ->chunkById(200, function ($costos) use ($detector): void {
                foreach ($costos as $costo) {
                    $detector->evaluar($costo);
                }
            });
    }
}
