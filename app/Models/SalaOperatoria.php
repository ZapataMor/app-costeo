<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Database\Factories\SalaOperatoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sala operatoria con su costo por hora de funcionamiento.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $nombre
 * @property string|null $ubicacion
 * @property string $costo_hora
 * @property list<string>|null $equipamiento
 * @property bool $activa
 */
class SalaOperatoria extends Model
{
    /** @use HasFactory<SalaOperatoriaFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'salas_operatorias';

    protected $fillable = [
        'hospital_id',
        'nombre',
        'ubicacion',
        'costo_hora',
        'equipamiento',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'costo_hora' => 'decimal:2',
            'equipamiento' => 'array',
            'activa' => 'boolean',
        ];
    }

    /** @return HasMany<Cirugia, $this> */
    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'sala_operatoria_id');
    }
}
