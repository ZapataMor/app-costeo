<?php

namespace App\Models;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\NivelConfiabilidad;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use App\Services\Costing\ActivarCategoriaCif;
use Carbon\CarbonInterface;
use Database\Factories\ConceptoCostoIndirectoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bolsa de costo indirecto con su inductor y su vigencia.
 *
 * Reemplaza al `factor_indirecto` único del hospital por conceptos que se
 * reparten con una base de asignación explícita. Un concepto solo entra al
 * costeo si está activo y vigente a la fecha de la cirugía.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $nombre
 * @property CategoriaCif $categoria
 * @property BaseAsignacionCif $base_asignacion
 * @property string|null $monto_mensual
 * @property string|null $porcentaje
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property bool $activo
 * @property string|null $fuente
 * @property NivelConfiabilidad $nivel_confiabilidad
 */
class ConceptoCostoIndirecto extends Model
{
    /** @use HasFactory<ConceptoCostoIndirectoFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'conceptos_costo_indirecto';

    /**
     * `activo` NO es asignable en masa: encender una bolsa excluye del costo
     * directo el componente equivalente, y las dos cosas tienen que ocurrir
     * en la misma transacción. Solo ActivarCategoriaCif lo escribe.
     *
     * @see ActivarCategoriaCif
     */
    protected $fillable = [
        'hospital_id',
        'nombre',
        'categoria',
        'base_asignacion',
        'monto_mensual',
        'porcentaje',
        'vigente_desde',
        'vigente_hasta',
        'fuente',
        'nivel_confiabilidad',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaCif::class,
            'base_asignacion' => BaseAsignacionCif::class,
            'monto_mensual' => 'decimal:2',
            'porcentaje' => 'decimal:4',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'activo' => 'boolean',
            'nivel_confiabilidad' => NivelConfiabilidad::class,
        ];
    }

    /** ¿Cubre este concepto la fecha dada? (`vigente_hasta` null = abierta) */
    public function vigenteEn(CarbonInterface $fecha): bool
    {
        if ($fecha->lessThan($this->vigente_desde)) {
            return false;
        }

        return $this->vigente_hasta === null
            || $fecha->lessThanOrEqualTo($this->vigente_hasta);
    }

    /**
     * Conceptos que el costeo debe aplicar a una cirugía de esa fecha.
     *
     * @param  Builder<ConceptoCostoIndirecto>  $query
     * @return Builder<ConceptoCostoIndirecto>
     */
    public function scopeAplicablesEn(Builder $query, CarbonInterface $fecha): Builder
    {
        return $query
            ->where('activo', true)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(fn (Builder $q) => $q
                ->whereNull('vigente_hasta')
                ->orWhereDate('vigente_hasta', '>=', $fecha));
    }
}
