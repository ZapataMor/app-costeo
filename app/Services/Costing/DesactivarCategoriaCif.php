<?php

namespace App\Services\Costing;

use App\Enums\CategoriaCif;
use App\Enums\OrigenComponente;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Models\Scopes\HospitalScope;
use Illuminate\Support\Facades\DB;

/**
 * Inversa exacta de ActivarCategoriaCif: apaga las bolsas de la categoría y
 * devuelve el componente directo a `digitado`, en la misma transacción.
 *
 * El valor digitado nunca se borró —esa fue la razón de marcar el origen en
 * vez de forzar el campo a cero—, así que volver atrás es un cambio de
 * configuración y no una re-digitación.
 *
 * @see ActivarCategoriaCif
 */
class DesactivarCategoriaCif
{
    /** @return int conceptos desactivados */
    public function ejecutar(Hospital $hospital, CategoriaCif $categoria): int
    {
        $columnaOrigen = $categoria->columnaOrigen();

        return DB::transaction(function () use ($hospital, $categoria, $columnaOrigen): int {
            $desactivados = ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospital->id)
                ->where('categoria', $categoria->value)
                ->update(['activo' => false]);

            if ($columnaOrigen !== null) {
                $hospital->forceFill([
                    $columnaOrigen => OrigenComponente::Digitado->value,
                ])->save();
            }

            return $desactivados;
        });
    }
}
