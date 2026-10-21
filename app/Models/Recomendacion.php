<?php

namespace App\Models;

use App\Enums\EstadoRecomendacion;
use App\Enums\TipoRecomendacion;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Acción sugerida por el histórico, con su ahorro mensual estimado.
 *
 * @property int $id
 * @property int $hospital_id
 * @property TipoRecomendacion $tipo
 * @property string $titulo
 * @property string $descripcion
 * @property int|null $procedimiento_quirurgico_id
 * @property int|null $insumo_id
 * @property int|null $sala_operatoria_id
 * @property int|null $recurso_humano_id
 * @property string $ahorro_mensual_estimado
 * @property array<string, mixed>|null $evidencia
 * @property string $clave
 * @property EstadoRecomendacion $estado
 * @property string|null $nota
 * @property int|null $responsable_id
 * @property Carbon|null $fecha_compromiso
 * @property int|null $gestionada_por
 * @property Carbon|null $gestionada_en
 * @property Carbon|null $implementada_en
 * @property int|null $leccion_aprendida_id
 * @property Carbon $generada_en
 */
class Recomendacion extends Model
{
    use BelongsToHospital;

    protected $table = 'recomendaciones';

    protected $fillable = [
        'hospital_id',
        'tipo',
        'titulo',
        'descripcion',
        'procedimiento_quirurgico_id',
        'insumo_id',
        'sala_operatoria_id',
        'recurso_humano_id',
        'ahorro_mensual_estimado',
        'evidencia',
        'clave',
        'generada_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRecomendacion::class,
            'estado' => EstadoRecomendacion::class,
            'ahorro_mensual_estimado' => 'decimal:2',
            'evidencia' => 'array',
            'fecha_compromiso' => 'date',
            'gestionada_en' => 'datetime',
            'implementada_en' => 'datetime',
            'generada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<Insumo, $this> */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    /** @return BelongsTo<SalaOperatoria, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(SalaOperatoria::class, 'sala_operatoria_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<LeccionAprendida, $this> */
    public function leccion(): BelongsTo
    {
        return $this->belongsTo(LeccionAprendida::class, 'leccion_aprendida_id');
    }
}
