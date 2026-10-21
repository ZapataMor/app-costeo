<?php

namespace App\Models;

use App\Enums\SeveridadAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Regla de conocimiento de un hospital: un tipo de patrón con sus umbrales.
 *
 * @property int $id
 * @property int $hospital_id
 * @property TipoReglaConocimiento $tipo
 * @property string $nombre
 * @property string|null $descripcion
 * @property SeveridadAlerta $severidad
 * @property array<string, float|int> $parametros
 * @property bool $activa
 * @property Carbon|null $evaluada_en
 */
class ReglaConocimiento extends Model
{
    use BelongsToHospital;

    protected $table = 'reglas_conocimiento';

    protected $fillable = [
        'hospital_id',
        'tipo',
        'nombre',
        'descripcion',
        'severidad',
        'parametros',
        'activa',
        'evaluada_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoReglaConocimiento::class,
            'severidad' => SeveridadAlerta::class,
            'parametros' => 'array',
            'activa' => 'boolean',
            'evaluada_en' => 'datetime',
        ];
    }

    /** Valor de un parámetro, con el del catálogo como respaldo. */
    public function parametro(string $clave): float
    {
        return (float) ($this->parametros[$clave]
            ?? $this->tipo->parametrosPorDefecto()[$clave]['valor']
            ?? 0);
    }

    /** @return HasMany<AlertaConocimiento, $this> */
    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaConocimiento::class);
    }
}
