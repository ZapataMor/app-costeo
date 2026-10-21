<?php

namespace App\Models;

use App\Enums\CategoriaLeccion;
use App\Enums\CausaSobrecosto;
use App\Enums\EstadoLeccion;
use App\Enums\TipoLeccion;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Lección aprendida o mejor práctica: conocimiento tácito hecho explícito.
 *
 * Las publicadas en la red (`compartida_red`) se leen desde cualquier
 * hospital con `scopeDeLaRed()`, que salta el scope del tenant a propósito.
 *
 * @property int $id
 * @property int $hospital_id
 * @property TipoLeccion $tipo
 * @property string $titulo
 * @property string $contexto
 * @property string $leccion
 * @property string|null $recomendacion
 * @property CategoriaLeccion $categoria
 * @property CausaSobrecosto|null $causa
 * @property int|null $procedimiento_quirurgico_id
 * @property string|null $especialidad
 * @property int|null $alerta_sobrecosto_id
 * @property int|null $alerta_conocimiento_id
 * @property string|null $impacto_estimado_cop
 * @property EstadoLeccion $estado
 * @property int|null $autor_id
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property string|null $motivo_devolucion
 * @property Carbon|null $publicada_en
 * @property bool $compartida_red
 * @property Carbon|null $compartida_en
 * @property list<string>|null $etiquetas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LeccionAprendida extends Model
{
    use BelongsToHospital;

    protected $table = 'lecciones_aprendidas';

    protected $fillable = [
        'hospital_id',
        'tipo',
        'titulo',
        'contexto',
        'leccion',
        'recomendacion',
        'categoria',
        'causa',
        'procedimiento_quirurgico_id',
        'especialidad',
        'alerta_sobrecosto_id',
        'alerta_conocimiento_id',
        'impacto_estimado_cop',
        'etiquetas',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoLeccion::class,
            'categoria' => CategoriaLeccion::class,
            'causa' => CausaSobrecosto::class,
            'estado' => EstadoLeccion::class,
            'impacto_estimado_cop' => 'decimal:2',
            'revisado_en' => 'datetime',
            'publicada_en' => 'datetime',
            'compartida_red' => 'boolean',
            'compartida_en' => 'datetime',
            'etiquetas' => 'array',
        ];
    }

    /**
     * Lecciones publicadas en la red departamental, de cualquier hospital.
     *
     * @param  Builder<LeccionAprendida>  $query
     * @return Builder<LeccionAprendida>
     */
    public function scopeDeLaRed(Builder $query): Builder
    {
        return $query->withoutGlobalScopes()
            ->where('compartida_red', true)
            ->where('estado', EstadoLeccion::Publicada->value);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<AlertaSobrecosto, $this> */
    public function alertaSobrecosto(): BelongsTo
    {
        return $this->belongsTo(AlertaSobrecosto::class);
    }

    /** @return BelongsTo<AlertaConocimiento, $this> */
    public function alertaConocimiento(): BelongsTo
    {
        return $this->belongsTo(AlertaConocimiento::class);
    }

    /** @return BelongsToMany<Cirugia, $this> */
    public function cirugias(): BelongsToMany
    {
        return $this->belongsToMany(Cirugia::class, 'leccion_cirugia')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function valoraciones(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'leccion_valoraciones')->withTimestamps();
    }
}
