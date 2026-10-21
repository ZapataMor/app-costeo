<?php

namespace App\Models;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Hallazgo de una regla de conocimiento, con su impacto en pesos.
 *
 * @property int $id
 * @property int $hospital_id
 * @property int|null $regla_conocimiento_id
 * @property TipoReglaConocimiento $tipo
 * @property SeveridadAlerta $severidad
 * @property string $titulo
 * @property string $descripcion
 * @property int|null $cirugia_id
 * @property int|null $procedimiento_quirurgico_id
 * @property int|null $sala_operatoria_id
 * @property int|null $recurso_humano_id
 * @property string|null $periodo
 * @property string|null $valor
 * @property string|null $umbral
 * @property string|null $impacto_cop
 * @property array<string, mixed>|null $datos
 * @property string $clave
 * @property EstadoAlerta $estado
 * @property CausaSobrecosto|null $causa
 * @property string|null $comentario
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property Carbon|null $created_at
 */
class AlertaConocimiento extends Model
{
    use BelongsToHospital;

    protected $table = 'alertas_conocimiento';

    protected $fillable = [
        'hospital_id',
        'regla_conocimiento_id',
        'tipo',
        'severidad',
        'titulo',
        'descripcion',
        'cirugia_id',
        'procedimiento_quirurgico_id',
        'sala_operatoria_id',
        'recurso_humano_id',
        'periodo',
        'valor',
        'umbral',
        'impacto_cop',
        'datos',
        'clave',
        'estado',
        'causa',
        'comentario',
        'revisado_por',
        'revisado_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoReglaConocimiento::class,
            'severidad' => SeveridadAlerta::class,
            'valor' => 'decimal:4',
            'umbral' => 'decimal:4',
            'impacto_cop' => 'decimal:2',
            'datos' => 'array',
            'estado' => EstadoAlerta::class,
            'causa' => CausaSobrecosto::class,
            'revisado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReglaConocimiento, $this> */
    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaConocimiento::class, 'regla_conocimiento_id');
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }

    /** @return BelongsTo<ProcedimientoQuirurgico, $this> */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(ProcedimientoQuirurgico::class, 'procedimiento_quirurgico_id');
    }

    /** @return BelongsTo<SalaOperatoria, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(SalaOperatoria::class, 'sala_operatoria_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** @return HasMany<LeccionAprendida, $this> */
    public function lecciones(): HasMany
    {
        return $this->hasMany(LeccionAprendida::class);
    }
}
