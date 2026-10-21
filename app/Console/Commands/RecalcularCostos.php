<?php

namespace App\Console\Commands;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\Scopes\HospitalScope;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Console\Command;

/**
 * Vuelve a pasar por el motor las cirugías ya costeadas.
 *
 * Recostear no cambia el costo de nada: el motor lee las tarifas y los
 * parámetros CIF congelados en cada cirugía. Sirve cuando el motor gana un
 * cálculo nuevo —como el costo estándar y su desviación— que el histórico
 * todavía no tiene.
 */
class RecalcularCostos extends Command
{
    protected $signature = 'costeo:recalcular {--hospital= : Limita el recosteo a un hospital}';

    protected $description = 'Recostea las cirugías realizadas con su snapshot (añade estándar y desviación al histórico)';

    public function handle(TdabcCostingService $motor): int
    {
        $consulta = Cirugia::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('estado', EstadoCirugia::Realizada->value)
            ->has('costo')
            ->when(
                $this->option('hospital') !== null,
                fn ($query) => $query->where('hospital_id', (int) $this->option('hospital')),
            );

        $total = $consulta->count();
        $barra = $this->output->createProgressBar($total);
        $anterior = HospitalContext::id();

        try {
            $consulta->chunkById(100, function ($cirugias) use ($motor, $barra): void {
                foreach ($cirugias as $cirugia) {
                    // El contexto del hospital de cada cirugía: las relaciones
                    // con scope global se resuelven dentro de su tenant.
                    HospitalContext::set($cirugia->hospital_id);
                    $motor->calcular($cirugia);
                    $barra->advance();
                }
            });
        } finally {
            HospitalContext::set($anterior);
        }

        $barra->finish();
        $this->newLine(2);
        $this->info("{$total} cirugías recosteadas.");

        return self::SUCCESS;
    }
}
