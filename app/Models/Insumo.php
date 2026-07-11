<?php

namespace App\Models;

use App\Enums\NivelConfiabilidad;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\InsumoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Insumo quirúrgico: medicamento (con código ATC), dispositivo o material.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $codigo
 * @property string $nombre
 * @property string $categoria
 * @property string|null $codigo_atc
 * @property string $unidad
 * @property string $costo_unitario
 * @property bool $activo
 * @property string|null $fuente
 * @property NivelConfiabilidad $nivel_confiabilidad
 */
class Insumo extends Model
{
    /** @use HasFactory<InsumoFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'insumos';

    protected $fillable = [
        'hospital_id',
        'codigo',
        'nombre',
        'categoria',
        'codigo_atc',
        'unidad',
        'costo_unitario',
        'activo',
        'fuente',
        'nivel_confiabilidad',
    ];

    protected function casts(): array
    {
        return [
            'costo_unitario' => 'decimal:2',
            'activo' => 'boolean',
            'nivel_confiabilidad' => NivelConfiabilidad::class,
        ];
    }

    /** @return HasMany<ConsumoInsumo, $this> */
    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoInsumo::class);
    }
}
