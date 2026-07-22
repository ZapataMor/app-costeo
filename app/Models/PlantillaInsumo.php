<?php

namespace App\Models;

use App\Enums\FaseCiclo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Insumo que un procedimiento consume siempre: línea de su plantilla.
 *
 * @property int $id
 * @property int $procedimiento_quirurgico_id
 * @property int $insumo_id
 * @property FaseCiclo $fase
 * @property string $cantidad
 * @property bool $opcional
 */
class PlantillaInsumo extends Model
{
    protected $table = 'plantilla_insumos';

    protected $fillable = [
        'procedimiento_quirurgico_id',
        'insumo_id',
        'fase',
        'cantidad',
        'opcional',
    ];

    protected function casts(): array
    {
        return [
            'fase' => FaseCiclo::class,
            'cantidad' => 'decimal:2',
            'opcional' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<Insumo, $this> */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
