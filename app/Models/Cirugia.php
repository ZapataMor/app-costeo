<?php

namespace App\Models;

use App\Enums\EstadoCirugia;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Carbon\CarbonInterface;
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
 * @property int|null $registrado_por
 * @property int $paciente_id
 * @property int|null $sala_operatoria_id
 * @property Carbon $fecha
 * @property Carbon|null $hora_ingreso_paciente
 * @property Carbon $hora_inicio
 * @property Carbon|null $hora_incision
 * @property Carbon|null $hora_cierre
 * @property Carbon|null $hora_fin
 * @property Carbon|null $hora_salida_recuperacion
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
        'registrado_por',
        'paciente_id',
        'sala_operatoria_id',
        'fecha',
        'hora_ingreso_paciente',
        'hora_inicio',
        'hora_incision',
        'hora_cierre',
        'hora_fin',
        'hora_salida_recuperacion',
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
            'hora_ingreso_paciente' => 'datetime',
            'hora_inicio' => 'datetime',
            'hora_incision' => 'datetime',
            'hora_cierre' => 'datetime',
            'hora_fin' => 'datetime',
            'hora_salida_recuperacion' => 'datetime',
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

    /** Preparación: del ingreso del paciente a su entrada a sala. */
    public function minutosPrequirurgico(): ?int
    {
        return $this->minutosEntre($this->hora_ingreso_paciente, $this->hora_inicio);
    }

    /**
     * Tiempo quirúrgico neto: de incisión a cierre. La diferencia contra
     * `duracionMinutos()` es sala ocupada sin operar —inducción, posición,
     * asepsia, educción—, que es donde se esconde la ineficiencia de
     * quirófano.
     */
    public function minutosQuirurgicoNeto(): ?int
    {
        return $this->minutosEntre($this->hora_incision, $this->hora_cierre);
    }

    /** Recuperación: de la salida de sala al egreso de URPA. */
    public function minutosRecuperacion(): ?int
    {
        return $this->minutosEntre($this->hora_fin, $this->hora_salida_recuperacion);
    }

    /**
     * Ciclo completo, del ingreso del paciente a su egreso. Nulo mientras
     * falte cualquiera de los dos extremos: un ciclo a medias no es un dato,
     * es una estimación disfrazada.
     */
    public function cicloTotalMinutos(): ?int
    {
        return $this->minutosEntre($this->hora_ingreso_paciente, $this->hora_salida_recuperacion);
    }

    /**
     * Qué marca falta para avanzar el cierre: la salida de sala o el egreso
     * de recuperación. Null cuando el ciclo ya está completo o el registro
     * se canceló.
     */
    public function pasoDeCierre(): ?string
    {
        if ($this->estado === EstadoCirugia::Cancelada->value) {
            return null;
        }

        if ($this->hora_fin === null) {
            return 'sala';
        }

        return $this->estado === EstadoCirugia::Realizada->value ? null : 'ciclo';
    }

    protected function minutosEntre(?CarbonInterface $desde, ?CarbonInterface $hasta): ?int
    {
        if ($desde === null || $hasta === null) {
            return null;
        }

        return (int) $desde->diffInMinutes($hasta);
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** Quién capturó el registro; nulo en datos sembrados o migrados. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
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
