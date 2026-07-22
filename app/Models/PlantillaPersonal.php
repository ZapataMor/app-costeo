<?php

namespace App\Models;

use App\Enums\FaseCiclo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rol que un procedimiento requiere siempre: línea de personal de su plantilla.
 *
 * `recurso_humano_id` es opcional a propósito: la plantilla dice que hacen
 * falta dos ayudantes, no quiénes son —eso lo define el turno—. Fijar la
 * persona solo tiene sentido donde de hecho siempre es la misma.
 *
 * @property int $id
 * @property int $procedimiento_quirurgico_id
 * @property string $rol
 * @property FaseCiclo $fase
 * @property int $cantidad
 * @property int|null $recurso_humano_id
 * @property int|null $minutos
 * @property bool $opcional
 */
class PlantillaPersonal extends Model
{
    protected $table = 'plantilla_personal';

    protected $fillable = [
        'procedimiento_quirurgico_id',
        'rol',
        'fase',
        'cantidad',
        'recurso_humano_id',
        'minutos',
        'opcional',
    ];

    protected function casts(): array
    {
        return [
            'fase' => FaseCiclo::class,
            'cantidad' => 'integer',
            'minutos' => 'integer',
            'opcional' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<RecursoHumano, $this> */
    public function recursoHumano(): BelongsTo
    {
        return $this->belongsTo(RecursoHumano::class);
    }
}
