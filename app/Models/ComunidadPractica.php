<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comunidad de práctica quirúrgica (tesis, componente 1): un foro por
 * especialidad donde el personal discute casos, lecciones y protocolos.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $nombre
 * @property string|null $especialidad
 * @property string|null $descripcion
 * @property bool $activa
 */
class ComunidadPractica extends Model
{
    use BelongsToHospital;

    protected $table = 'comunidades_practica';

    protected $fillable = [
        'hospital_id',
        'nombre',
        'especialidad',
        'descripcion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comunidad_miembros')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /** @return HasMany<HiloComunidad, $this> */
    public function hilos(): HasMany
    {
        return $this->hasMany(HiloComunidad::class);
    }
}
