<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Hilo de discusión de una comunidad, opcionalmente enlazado a lo que se
 * discute (lección, alerta, procedimiento o recomendación).
 *
 * @property int $id
 * @property int $comunidad_practica_id
 * @property int $hospital_id
 * @property int|null $autor_id
 * @property string $titulo
 * @property string $cuerpo
 * @property string|null $enlazable_tipo
 * @property int|null $enlazable_id
 * @property bool $fijado
 * @property bool $cerrado
 * @property int|null $leccion_aprendida_id
 * @property Carbon $ultima_actividad_en
 * @property Carbon|null $created_at
 */
class HiloComunidad extends Model
{
    use BelongsToHospital;

    /** Lo que un hilo puede enlazar, y su modelo. */
    public const ENLAZABLES = [
        'leccion' => LeccionAprendida::class,
        'alerta' => AlertaConocimiento::class,
        'alerta_sobrecosto' => AlertaSobrecosto::class,
        'procedimiento' => ProcedimientoQuirurgico::class,
        'recomendacion' => Recomendacion::class,
    ];

    protected $table = 'hilos_comunidad';

    protected $fillable = [
        'comunidad_practica_id',
        'hospital_id',
        'autor_id',
        'titulo',
        'cuerpo',
        'enlazable_tipo',
        'enlazable_id',
        'fijado',
        'cerrado',
        'leccion_aprendida_id',
        'ultima_actividad_en',
    ];

    protected function casts(): array
    {
        return [
            'fijado' => 'boolean',
            'cerrado' => 'boolean',
            'ultima_actividad_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<ComunidadPractica, $this> */
    public function comunidad(): BelongsTo
    {
        return $this->belongsTo(ComunidadPractica::class, 'comunidad_practica_id');
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /** @return HasMany<RespuestaHilo, $this> */
    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaHilo::class);
    }

    /** @return BelongsTo<LeccionAprendida, $this> */
    public function leccion(): BelongsTo
    {
        return $this->belongsTo(LeccionAprendida::class, 'leccion_aprendida_id');
    }

    /**
     * Lo enlazado, resuelto dentro del hospital del hilo.
     */
    public function enlazado(): ?Model
    {
        $clase = self::ENLAZABLES[$this->enlazable_tipo ?? ''] ?? null;

        if ($clase === null || $this->enlazable_id === null) {
            return null;
        }

        return $clase::query()->find($this->enlazable_id);
    }
}
