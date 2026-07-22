<?php

namespace App\Models;

use App\Enums\CausaSobrecosto;
use App\Enums\ComponenteCosto;
use App\Enums\EstadoAlerta;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\AlertaSobrecostoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sobrecosto detectado en una cirugía respecto al baseline de su
 * procedimiento, con el exceso ya descompuesto por componente.
 *
 * No lleva `Auditable`: la alerta la crea el motor de costeo, no una persona,
 * y auditarla como «Fulano creó la alerta» sería falso. Lo que sí se audita
 * —explícitamente, al revisarla— es la atribución de causa, que es el único
 * acto humano del ciclo.
 *
 * @property int $id
 * @property int $hospital_id
 * @property int $cirugia_id
 * @property int $procedimiento_quirurgico_id
 * @property string $costo_total
 * @property string $costo_esperado
 * @property string $exceso
 * @property string $exceso_pct
 * @property string|null $z
 * @property list<string> $criterios
 * @property int $n_baseline
 * @property array<string, mixed> $atribucion
 * @property ComponenteCosto $componente_dominante
 * @property EstadoAlerta $estado
 * @property CausaSobrecosto|null $causa
 * @property string|null $causa_detalle
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property Carbon $detectado_en
 */
class AlertaSobrecosto extends Model
{
    /** @use HasFactory<AlertaSobrecostoFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'alertas_sobrecosto';

    protected $fillable = [
        'hospital_id',
        'cirugia_id',
        'procedimiento_quirurgico_id',
        'costo_total',
        'costo_esperado',
        'exceso',
        'exceso_pct',
        'z',
        'criterios',
        'n_baseline',
        'atribucion',
        'componente_dominante',
        'estado',
        'causa',
        'causa_detalle',
        'revisado_por',
        'revisado_en',
        'detectado_en',
    ];

    protected function casts(): array
    {
        return [
            'costo_total' => 'decimal:2',
            'costo_esperado' => 'decimal:2',
            'exceso' => 'decimal:2',
            'exceso_pct' => 'decimal:4',
            'z' => 'decimal:3',
            'criterios' => 'array',
            'atribucion' => 'array',
            'componente_dominante' => ComponenteCosto::class,
            'estado' => EstadoAlerta::class,
            'causa' => CausaSobrecosto::class,
            'revisado_en' => 'datetime',
            'detectado_en' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** @param  Builder<$this>  $query */
    public function scopePendientes(Builder $query): void
    {
        $query->where('estado', EstadoAlerta::Pendiente->value);
    }

    /**
     * Sobrecosto que la revisión declaró evitable: la parte del exceso que el
     * hospital sí podía haber ahorrado. Nulo mientras no haya causa.
     */
    public function excesoEvitable(): ?float
    {
        if ($this->causa === null || ! $this->causa->evitable()) {
            return $this->causa === null ? null : 0.0;
        }

        return (float) $this->exceso;
    }
}
