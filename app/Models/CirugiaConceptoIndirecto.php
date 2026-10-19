<?php

namespace App\Models;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una bolsa CIF aplicada a una cirugía concreta.
 *
 * Guarda la línea entera —monto de la bolsa, denominador, tasa y unidades—
 * y no solo el resultado, porque el valor de auditoría está en poder rehacer
 * la cuenta sin el catálogo: «$28.000.000 ÷ 56.160 min × 120 min = $59.829».
 *
 * La suma de `monto_asignado` de una cirugía es exactamente su
 * `costo_indirecto`: el reparto usa mayor resto para que no falte ni sobre
 * un centavo.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $concepto_costo_indirecto_id
 * @property int $hospital_id
 * @property string $nombre_registrado
 * @property CategoriaCif $categoria_registrada
 * @property BaseAsignacionCif $base_asignacion_registrada
 * @property string|null $monto_mensual_registrado
 * @property string|null $porcentaje_registrado
 * @property int|null $denominador_registrado
 * @property string $tasa_registrada
 * @property string $unidades_aplicadas
 * @property string $monto_asignado
 */
class CirugiaConceptoIndirecto extends Model
{
    use BelongsToHospital;

    protected $table = 'cirugia_concepto_indirecto';

    protected $fillable = [
        'cirugia_id',
        'concepto_costo_indirecto_id',
        'hospital_id',
        'nombre_registrado',
        'categoria_registrada',
        'base_asignacion_registrada',
        'monto_mensual_registrado',
        'porcentaje_registrado',
        'denominador_registrado',
        'tasa_registrada',
        'unidades_aplicadas',
        'monto_asignado',
    ];

    protected function casts(): array
    {
        return [
            'categoria_registrada' => CategoriaCif::class,
            'base_asignacion_registrada' => BaseAsignacionCif::class,
            'monto_mensual_registrado' => 'decimal:2',
            'porcentaje_registrado' => 'decimal:4',
            'denominador_registrado' => 'integer',
            'tasa_registrada' => 'decimal:6',
            'unidades_aplicadas' => 'decimal:2',
            'monto_asignado' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }

    /** @return BelongsTo<ConceptoCostoIndirecto, $this> */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoCostoIndirecto::class, 'concepto_costo_indirecto_id');
    }
}
