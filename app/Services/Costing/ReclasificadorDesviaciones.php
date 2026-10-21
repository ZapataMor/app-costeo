<?php

namespace App\Services\Costing;

use App\Enums\ClasificacionDesviacion;
use App\Models\Hospital;
use Illuminate\Support\Facades\DB;

/**
 * Vuelve a clasificar las desviaciones ya costeadas de un hospital cuando
 * cambian sus umbrales.
 *
 * La clasificación es una lente de gestión, no parte del costo: el costo
 * estándar y la desviación siguen congelados; solo cambia la etiqueta. Por
 * eso se reescribe en bloque, una banda por sentencia, en vez de recostear
 * cada cirugía.
 */
class ReclasificadorDesviaciones
{
    public function ejecutar(Hospital $hospital): int
    {
        $base = fn () => DB::table('costos_cirugia')
            ->where('hospital_id', $hospital->id)
            ->whereNotNull('desviacion_pct');

        return DB::transaction(fn (): int => $base()
            ->where('desviacion_pct', '<=', $hospital->umbral_subejecucion)
            ->update(['clasificacion_desviacion' => ClasificacionDesviacion::Subejecucion->value])
            + $base()
                ->where('desviacion_pct', '>', $hospital->umbral_sobrecosto_alto)
                ->update(['clasificacion_desviacion' => ClasificacionDesviacion::SobrecostoAlto->value])
            + $base()
                ->where('desviacion_pct', '>=', $hospital->umbral_sobrecosto_leve)
                ->where('desviacion_pct', '<=', $hospital->umbral_sobrecosto_alto)
                ->update(['clasificacion_desviacion' => ClasificacionDesviacion::SobrecostoLeve->value])
            + $base()
                ->where('desviacion_pct', '>', $hospital->umbral_subejecucion)
                ->where('desviacion_pct', '<', $hospital->umbral_sobrecosto_leve)
                ->update(['clasificacion_desviacion' => ClasificacionDesviacion::EnRango->value]));
    }
}
