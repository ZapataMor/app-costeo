<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\CirugiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Evento quirúrgico: el objeto de costo del modelo TDABC.
 *
 * @property int $id
 * @property int $hospital_id
 * @property int $paciente_id
 * @property int|null $sala_operatoria_id
 * @property Carbon $fecha
 * @property Carbon $hora_inicio
 * @property Carbon|null $hora_fin
 * @property string $tipo
 * @property string $estado
 * @property string|null $diagnostico_cie10
 * @property int|null $minutos_disponibles_mes_registrado
 * @property float|null $factor_indirecto_registrado
 * @property string|null $costo_hora_sala_registrado
 */
class Cirugia extends Model
{
    /** @use HasFactory<CirugiaFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'cirugias';

    protected $fillable = [
        'hospital_id',
        'paciente_id',
        'sala_operatoria_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'tipo',
        'estado',
        'diagnostico_cie10',
        'observaciones',
        'minutos_disponibles_mes_registrado',
        'factor_indirecto_registrado',
        'costo_hora_sala_registrado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
            'minutos_disponibles_mes_registrado' => 'integer',
            'factor_indirecto_registrado' => 'float',
            'costo_hora_sala_registrado' => 'decimal:2',
        ];
    }

    /** Duración real en minutos (base del costo de sala). */
    public function duracionMinutos(): ?int
    {
        if ($this->hora_fin === null) {
            return null;
        }

        return (int) $this->hora_inicio->diffInMinutes($this->hora_fin);
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<SalaOperatoria, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(SalaOperatoria::class, 'sala_operatoria_id');
    }

    /** @return BelongsToMany<ProcedimientoQuirurgico, $this> */
    public function procedimientos(): BelongsToMany
    {
        return $this->belongsToMany(ProcedimientoQuirurgico::class, 'cirugia_procedimiento')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    public function procedimientoPrincipal(): ?ProcedimientoQuirurgico
    {
        return $this->procedimientos->firstWhere('pivot.es_principal', true)
            ?? $this->procedimientos->first();
    }

    /** @return HasMany<MiembroEquipoQuirurgico, $this> */
    public function equipoQuirurgico(): HasMany
    {
        return $this->hasMany(MiembroEquipoQuirurgico::class);
    }

    /** @return HasMany<ConsumoInsumo, $this> */
    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoInsumo::class);
    }

    /** @return BelongsToMany<EquipoMedico, $this> */
    public function equiposMedicos(): BelongsToMany
    {
        return $this->belongsToMany(EquipoMedico::class, 'cirugia_equipo_medico')
            ->withPivot('minutos_uso', 'costo_hora_registrado')
            ->withTimestamps();
    }

    /** @return HasOne<CostoCirugia, $this> */
    public function costo(): HasOne
    {
        return $this->hasOne(CostoCirugia::class);
    }

    /** @return HasOne<ResultadoClinico, $this> */
    public function resultadoClinico(): HasOne
    {
        return $this->hasOne(ResultadoClinico::class);
    }

    /** @return HasOne<Facturacion, $this> */
    public function facturacion(): HasOne
    {
        return $this->hasOne(Facturacion::class);
    }
}
