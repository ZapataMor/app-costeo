<?php

namespace App\Models;

use App\Enums\NivelConfiabilidad;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\ProcedimientoQuirurgicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Procedimiento quirúrgico según codificación CUPS.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $codigo_cups
 * @property string $nombre
 * @property string $especialidad
 * @property string $complejidad
 * @property int $duracion_estimada_minutos
 * @property int|null $minutos_prequirurgico
 * @property int|null $minutos_recuperacion
 * @property int|null $minutos_recambio
 * @property string|null $tarifa_soat
 * @property string|null $fuente
 * @property NivelConfiabilidad $nivel_confiabilidad
 * @property-read Pivot|null $pivot
 */
class ProcedimientoQuirurgico extends Model
{
    /** @use HasFactory<ProcedimientoQuirurgicoFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'procedimientos_quirurgicos';

    protected $fillable = [
        'hospital_id',
        'codigo_cups',
        'nombre',
        'especialidad',
        'complejidad',
        'duracion_estimada_minutos',
        'minutos_prequirurgico',
        'minutos_recuperacion',
        'minutos_recambio',
        'tarifa_soat',
        'fuente',
        'nivel_confiabilidad',
    ];

    protected function casts(): array
    {
        return [
            'duracion_estimada_minutos' => 'integer',
            'minutos_prequirurgico' => 'integer',
            'minutos_recuperacion' => 'integer',
            'minutos_recambio' => 'integer',
            'tarifa_soat' => 'decimal:2',
            'nivel_confiabilidad' => NivelConfiabilidad::class,
        ];
    }

    /**
     * Minutos estándar del ciclo completo: preparación + sala + recuperación
     * + recambio. Las fases sin dato levantado cuentan como cero, así que
     * mientras no se capturen el total equivale al tiempo de sala de siempre.
     */
    public function cicloTotalMinutos(): int
    {
        return $this->duracion_estimada_minutos
            + ($this->minutos_prequirurgico ?? 0)
            + ($this->minutos_recuperacion ?? 0)
            + ($this->minutos_recambio ?? 0);
    }

    /** @return BelongsToMany<Cirugia, $this> */
    public function cirugias(): BelongsToMany
    {
        return $this->belongsToMany(Cirugia::class, 'cirugia_procedimiento')
            ->withPivot('es_principal')
            ->withTimestamps();
    }
}
