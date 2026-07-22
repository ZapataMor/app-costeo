<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipo médico que un procedimiento usa siempre: línea de su plantilla.
 *
 * `minutos_uso` nulo significa «lo que dure la sala», que es el caso normal
 * de la torre de laparoscopia o el electrobisturí.
 *
 * @property int $id
 * @property int $procedimiento_quirurgico_id
 * @property int $equipo_medico_id
 * @property int|null $minutos_uso
 * @property bool $opcional
 */
class PlantillaEquipo extends Model
{
    protected $table = 'plantilla_equipos';

    protected $fillable = [
        'procedimiento_quirurgico_id',
        'equipo_medico_id',
        'minutos_uso',
        'opcional',
    ];

    protected function casts(): array
    {
        return [
            'minutos_uso' => 'integer',
            'opcional' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<EquipoMedico, $this> */
    public function equipoMedico(): BelongsTo
    {
        return $this->belongsTo(EquipoMedico::class);
    }
}
