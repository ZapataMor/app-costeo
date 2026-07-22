<?php

namespace App\Models;

use App\Enums\FaseCiclo;
use Database\Factories\ConsumoInsumoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consumo de un insumo en una cirugía, con snapshot del costo unitario.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $insumo_id
 * @property FaseCiclo $fase
 * @property string $cantidad
 * @property string $costo_unitario_registrado
 * @property string $costo_total
 */
class ConsumoInsumo extends Model
{
    /** @use HasFactory<ConsumoInsumoFactory> */
    use HasFactory;

    protected $table = 'consumo_insumos';

    protected $fillable = [
        'cirugia_id',
        'insumo_id',
        'fase',
        'cantidad',
        'costo_unitario_registrado',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'fase' => FaseCiclo::class,
            'cantidad' => 'decimal:2',
            'costo_unitario_registrado' => 'decimal:2',
            'costo_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }

    /** @return BelongsTo<Insumo, $this> */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
