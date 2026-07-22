<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use App\Models\ProcedimientoQuirurgico;
use App\Services\Plantillas\GeneradorPlantilla;
use App\Support\HospitalContext;
use Illuminate\Console\Command;

/**
 * Deduce las plantillas de todos los procedimientos a partir del histórico.
 *
 * Sirve para poner en marcha un hospital que ya venía registrando cirugías
 * sin protocolos: en vez de escribir las plantillas a mano, se derivan de lo
 * que se ha usado y después se corrigen desde la pantalla de plantilla.
 */
class GenerarPlantillasCommand extends Command
{
    protected $signature = 'plantillas:generar
                            {--hospital= : ID del hospital; por defecto, todos}
                            {--forzar : Regenera también los procedimientos que ya tienen plantilla}';

    protected $description = 'Deduce la plantilla estándar de cada procedimiento desde sus cirugías registradas';

    public function handle(GeneradorPlantilla $generador): int
    {
        $hospitales = Hospital::query()
            ->when($this->option('hospital'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($hospitales->isEmpty()) {
            $this->error('No hay hospitales que procesar.');

            return self::FAILURE;
        }

        $anterior = HospitalContext::id();
        $totalLineas = 0;

        foreach ($hospitales as $hospital) {
            HospitalContext::set($hospital->id);

            $this->line("<info>{$hospital->nombre}</info>");

            foreach (ProcedimientoQuirurgico::orderBy('nombre')->get() as $procedimiento) {
                $yaTiene = $procedimiento->plantillaInsumos()->exists()
                    || $procedimiento->plantillaPersonal()->exists()
                    || $procedimiento->plantillaEquipos()->exists();

                if ($yaTiene && ! $this->option('forzar')) {
                    $this->line("  · {$procedimiento->nombre}: ya tiene plantilla (use --forzar)");

                    continue;
                }

                $lineas = $generador->generar($procedimiento);
                $totalLineas += $lineas;

                $this->line($lineas > 0
                    ? "  ✓ {$procedimiento->nombre}: {$lineas} líneas"
                    : "  · {$procedimiento->nombre}: sin histórico suficiente");
            }
        }

        HospitalContext::set($anterior);

        $this->newLine();
        $this->info("Listo: {$totalLineas} líneas de plantilla generadas.");

        return self::SUCCESS;
    }
}
