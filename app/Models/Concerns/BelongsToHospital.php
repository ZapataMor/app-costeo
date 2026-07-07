<?php

namespace App\Models\Concerns;

use App\Models\Hospital;
use App\Models\Scopes\HospitalScope;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla el modelo por tenant: filtra toda consulta por el hospital
 * activo y lo asigna automáticamente al crear registros.
 */
trait BelongsToHospital
{
    protected static function bootBelongsToHospital(): void
    {
        static::addGlobalScope(new HospitalScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('hospital_id') === null && ($id = HospitalContext::id()) !== null) {
                $model->setAttribute('hospital_id', $id);
            }
        });
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
