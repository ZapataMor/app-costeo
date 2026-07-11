<?php

namespace App\Models;

use App\Enums\NivelConfiabilidad;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\ProcedimientoQuirurgicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
 * @property string|null $tarifa_soat
 * @property string|null $fuente
 * @property NivelConfiabilidad $nivel_confiabilidad
 */
class ProcedimientoQuirurgico extends Model
{
    /** @use HasFactory<ProcedimientoQuirurgicoFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'procedimientos_quirurgicos';

    protected $fillable = [
        'hospital_id',
        'codigo_cups',
        'nombre',
        'especialidad',
        'complejidad',
        'duracion_estimada_minutos',
        'tarifa_soat',
        'fuente',
        'nivel_confiabilidad',
    ];

    protected function casts(): array
    {
        return [
            'duracion_estimada_minutos' => 'integer',
            'tarifa_soat' => 'decimal:2',
            'nivel_confiabilidad' => NivelConfiabilidad::class,
        ];
    }

    /** @return BelongsToMany<Cirugia, $this> */
    public function cirugias(): BelongsToMany
    {
        return $this->belongsToMany(Cirugia::class, 'cirugia_procedimiento')
            ->withPivot('es_principal')
            ->withTimestamps();
    }
}
