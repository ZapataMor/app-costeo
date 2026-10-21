<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Respuesta a un hilo. Hereda el aislamiento del hilo: no tiene
 * hospital_id propio y solo se alcanza a través de él.
 *
 * @property int $id
 * @property int $hilo_comunidad_id
 * @property int|null $autor_id
 * @property string $cuerpo
 * @property bool $es_solucion
 * @property Carbon|null $created_at
 */
class RespuestaHilo extends Model
{
    protected $table = 'respuestas_hilo';

    protected $fillable = [
        'hilo_comunidad_id',
        'autor_id',
        'cuerpo',
        'es_solucion',
    ];

    protected function casts(): array
    {
        return [
            'es_solucion' => 'boolean',
        ];
    }

    /** @return BelongsTo<HiloComunidad, $this> */
    public function hilo(): BelongsTo
    {
        return $this->belongsTo(HiloComunidad::class, 'hilo_comunidad_id');
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
