<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Database\Factories\EquipoMedicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Equipo médico (laparoscopio, electrobisturí, etc.) con costo por hora
 * de uso y datos para depreciación.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $nombre
 * @property string|null $codigo
 * @property string|null $valor_adquisicion
 * @property int|null $vida_util_anios
 * @property string $costo_hora
 * @property bool $activo
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 */
class EquipoMedico extends Model
{
    /** @use HasFactory<EquipoMedicoFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'equipos_medicos';

    protected $fillable = [
        'hospital_id',
        'nombre',
        'codigo',
        'valor_adquisicion',
        'vida_util_anios',
        'costo_hora',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'valor_adquisicion' => 'decimal:2',
            'vida_util_anios' => 'integer',
            'costo_hora' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /** Depreciación mensual lineal: valor de adquisición ÷ (vida útil × 12). */
    public function depreciacionMensual(): ?float
    {
        if ($this->valor_adquisicion === null || ! $this->vida_util_anios) {
            return null;
        }

        return (float) $this->valor_adquisicion / ($this->vida_util_anios * 12);
    }

    /** @return BelongsToMany<Cirugia, $this> */
    public function cirugias(): BelongsToMany
    {
        return $this->belongsToMany(Cirugia::class, 'cirugia_equipo_medico')
            ->withPivot('minutos_uso')
            ->withTimestamps();
    }
}
