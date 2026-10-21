<?php

namespace App\Models;

use App\Enums\ClasificacionDesviacion;
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
 * @property string|null $costo_estandar
 * @property string|null $desviacion
 * @property string|null $desviacion_pct
 * @property ClasificacionDesviacion|null $clasificacion_desviacion
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
        'costo_estandar',
        'desviacion',
        'desviacion_pct',
        'clasificacion_desviacion',
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
            'costo_estandar' => 'decimal:2',
            'desviacion' => 'decimal:2',
            'desviacion_pct' => 'decimal:4',
            'clasificacion_desviacion' => ClasificacionDesviacion::class,
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
