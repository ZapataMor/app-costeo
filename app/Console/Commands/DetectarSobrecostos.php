<?php

namespace App\Console\Commands;

use App\Models\CostoCirugia;
use App\Models\Scopes\HospitalScope;
use App\Services\Costing\DetectorSobrecostos;
use Illuminate\Console\Command;

/**
 * Barre el histórico ya costeado y genera las alertas que le falten.
 *
 * El detector se dispara al costear, así que un hospital que ya venía usando
 * el sistema arrancaría con la bandeja vacía y toda su historia sin revisar
 * —justo donde están los sobrecostos que nunca nadie miró—. Este comando la
 * llena una vez; después, el costeo la mantiene al día solo.
 *
 * Es idempotente: reejecutarlo no duplica alertas ni pisa las ya revisadas.
 */
class DetectarSobrecostos extends Command
{
    protected $signature = 'costeo:detectar-sobrecostos {--hospital= : Limita el barrido a un hospital}';

    protected $description = 'Genera alertas de sobrecosto sobre las cirugías ya costeadas';

    public function handle(DetectorSobrecostos $detector): int
    {
        $consulta = CostoCirugia::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->with('cirugia.procedimientos')
            ->when(
                $this->option('hospital') !== null,
                fn ($query) => $query->where('hospital_id', (int) $this->option('hospital')),
            );

        $total = $consulta->count();

        if ($total === 0) {
            $this->info('No hay cirugías costeadas que analizar.');

            return self::SUCCESS;
        }

        $barra = $this->output->createProgressBar($total);
        $detectadas = 0;

        // Por lotes: un hospital con años de historia no cabe en memoria de
        // una sola consulta.
        $consulta->chunkById(200, function ($costos) use ($detector, $barra, &$detectadas): void {
            foreach ($costos as $costo) {
                if ($detector->evaluar($costo) !== null) {
                    $detectadas++;
                }

                $barra->advance();
            }
        });

        $barra->finish();
        $this->newLine(2);
        $this->info("{$detectadas} alertas de sobrecosto sobre {$total} cirugías costeadas.");

        return self::SUCCESS;
    }
}
