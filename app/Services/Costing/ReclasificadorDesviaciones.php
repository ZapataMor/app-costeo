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
 * eso se reescribe en bloque con un CASE en vez de recostear cada cirugía.
 */
class ReclasificadorDesviaciones
{
    public function ejecutar(Hospital $hospital): int
    {
        return DB::table('costos_cirugia')
            ->where('hospital_id', $hospital->id)
            ->whereNotNull('desviacion_pct')
            ->update([
                'clasificacion_desviacion' => DB::raw(sprintf(
                    'case when desviacion_pct <= %F then \'%s\' when desviacion_pct > %F then \'%s\' when desviacion_pct >= %F then \'%s\' else \'%s\' end',
                    $hospital->umbral_subejecucion,
                    ClasificacionDesviacion::Subejecucion->value,
                    $hospital->umbral_sobrecosto_alto,
                    ClasificacionDesviacion::SobrecostoAlto->value,
                    $hospital->umbral_sobrecosto_leve,
                    ClasificacionDesviacion::SobrecostoLeve->value,
                    ClasificacionDesviacion::EnRango->value,
                )),
            ]);
    }
}
