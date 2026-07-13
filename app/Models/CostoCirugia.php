<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\CostoCirugiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Resultado del motor de costeo TDABC para una cirugía:
 * desglose por componente, costo directo, indirecto y total.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $hospital_id
 * @property string $costo_recurso_humano
 * @property string $costo_sala
 * @property string $costo_equipos
 * @property string $costo_insumos
 * @property string $costo_directo
 * @property string $costo_indirecto
 * @property string $costo_total
 * @property array<string, mixed>|null $detalle
 * @property Carbon|null $calculado_en
 */
class CostoCirugia extends Model
{
    /** @use HasFactory<CostoCirugiaFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'costos_cirugia';

    protected $fillable = [
        'cirugia_id',
        'hospital_id',
        'costo_recurso_humano',
        'costo_sala',
        'costo_equipos',
        'costo_insumos',
        'costo_directo',
        'costo_indirecto',
        'costo_total',
        'detalle',
        'calculado_en',
    ];

    protected function casts(): array
    {
        return [
            'costo_recurso_humano' => 'decimal:2',
            'costo_sala' => 'decimal:2',
            'costo_equipos' => 'decimal:2',
            'costo_insumos' => 'decimal:2',
            'costo_directo' => 'decimal:2',
            'costo_indirecto' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'detalle' => 'array',
            'calculado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }
}
