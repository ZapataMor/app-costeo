<?php

namespace App\Models;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\OrigenComponente;
use App\Enums\RolQuirurgico;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\HospitalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ente hospitalario: es el tenant del sistema. Todos los datos de dominio
 * cuelgan de un hospital_id.
 *
 * @property int $id
 * @property string $nombre
 * @property string $nit
 * @property string $nivel_complejidad
 * @property string|null $municipio
 * @property string $departamento
 * @property int $horas_dia
 * @property int $dias_mes
 * @property int $minutos_efectivos_hora
 * @property float $factor_indirecto
 * @property OrigenComponente $origen_infraestructura
 * @property OrigenComponente $origen_depreciacion_equipos
 * @property OrigenComponente $origen_personal_indirecto
 */
class Hospital extends Model
{
    /** @use HasFactory<HospitalFactory> */
    use Auditable, HasFactory;

    protected $table = 'hospitales';

    /**
     * Los orígenes se quedan fuera de `$fillable` —solo ActivarCategoriaCif
     * los escribe— pero necesitan default en el modelo: `create()` no relee
     * la fila, así que sin esto un hospital recién creado traería null y
     * `origenDe()` no podría responder hasta el primer refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'origen_infraestructura' => 'digitado',
        'origen_depreciacion_equipos' => 'digitado',
        'origen_personal_indirecto' => 'digitado',
    ];

    protected $fillable = [
        'nombre',
        'nit',
        'nivel_complejidad',
        'municipio',
        'departamento',
        'horas_dia',
        'dias_mes',
        'minutos_efectivos_hora',
        'factor_indirecto',
    ];

    protected function casts(): array
    {
        return [
            'horas_dia' => 'integer',
            'dias_mes' => 'integer',
            'minutos_efectivos_hora' => 'integer',
            'factor_indirecto' => 'float',
            'origen_infraestructura' => OrigenComponente::class,
            'origen_depreciacion_equipos' => OrigenComponente::class,
            'origen_personal_indirecto' => OrigenComponente::class,
        ];
    }

    /**
     * Origen del componente directo que la categoría podría duplicar.
     * Las categorías sin equivalente en el directo siempre son `digitado`.
     */
    public function origenDe(CategoriaCif $categoria): OrigenComponente
    {
        $columna = $categoria->columnaOrigen();

        if ($columna === null) {
            return OrigenComponente::Digitado;
        }

        return $this->getAttribute($columna);
    }

    /**
     * Mapa completo de orígenes, para congelarlo en la cirugía: sin él,
     * recostear una cirugía vieja cambiaría según el switch de hoy.
     *
     * @return array<string, string>
     */
    public function origenesDeComponentes(): array
    {
        $origenes = [];

        foreach (CategoriaCif::cases() as $categoria) {
            if ($categoria->solapaConElDirecto()) {
                $origenes[$categoria->value] = $this->origenDe($categoria)->value;
            }
        }

        return $origenes;
    }

    /**
     * Capacidad práctica TDABC: minutos disponibles por recurso al mes.
     * Por defecto 12 h/día × 26 días × 60 min efectivos = 18.720 minutos.
     *
     * `minutos_efectivos_hora` es la porción productiva de cada hora: con 40
     * la capacidad baja a 12.480 y toda tarifa por minuto sube un 50 %.
     */
    public function minutosDisponiblesMes(): int
    {
        return $this->horas_dia * $this->dias_mes * $this->minutos_efectivos_hora;
    }

    /**
     * Capacidad mensual de TODOS los quirófanos activos del hospital.
     *
     * Es el denominador de las bolsas repartidas por minuto de quirófano.
     * Ojo con la diferencia frente a `minutosDisponiblesMes()`: aquella es la
     * capacidad de UN recurso —correcta para el costo/minuto de una persona—
     * y usarla aquí inflaría la tasa tantas veces como salas activas haya,
     * porque la bolsa mensual cubre todas las salas a la vez.
     */
    public function capacidadQuirofanosMes(): int
    {
        return $this->salasOperatorias()->where('activa', true)->count()
            * $this->minutosDisponiblesMes();
    }

    /**
     * Capacidad mensual sumada del personal quirúrgico activo.
     *
     * Denominador de las bolsas por minuto de personal. Solo cuenta los roles
     * que entran a sala: la bolsa de personal indirecto se reparte entre
     * quienes generan la actividad, no entre toda la nómina.
     */
    public function capacidadPersonalQuirurgicoMes(): int
    {
        return $this->recursosHumanos()
            ->where('activo', true)
            ->whereIn('rol', RolQuirurgico::values())
            ->count() * $this->minutosDisponiblesMes();
    }

    /**
     * Denominador de cada base de asignación, para congelarlo en la cirugía.
     * `porcentaje_directo` no divide nada y queda fuera del mapa.
     *
     * @return array<string, int>
     */
    public function denominadoresCif(): array
    {
        return [
            BaseAsignacionCif::MinutoQuirofano->value => $this->capacidadQuirofanosMes(),
            BaseAsignacionCif::MinutoPersonal->value => $this->capacidadPersonalQuirurgicoMes(),
        ];
    }

    /**
     * La misma fórmula de minutosDisponiblesMes() expresada en SQL.
     *
     * Los indicadores agregan con `sum()`/`group by` sobre miles de
     * participaciones y no pueden hidratarlas para reusar el método; sin este
     * punto único la capacidad quedaba escrita dos veces —una en PHP y otra
     * como literal en el SQL— y ya se habían separado una vez.
     */
    public static function expresionMinutosDisponiblesMes(string $tabla = 'hospitales'): string
    {
        return "{$tabla}.horas_dia * {$tabla}.dias_mes * {$tabla}.minutos_efectivos_hora";
    }

    /**
     * Capacidad de una sala en una ventana de fechas.
     *
     * La configuración del hospital es mensual (horas/día × días/mes), así que
     * comparar un trimestre contra la capacidad de un mes daba utilizaciones
     * tres veces más bajas de lo real.
     *
     * Los meses naturales completos usan el valor configurado tal cual; una
     * ventana arbitraria prorratea los días naturales a días operativos con
     * la proporción `dias_mes / 30,4375` (días promedio de un mes).
     *
     * Usa siempre la capacidad VIGENTE, no la del periodo consultado: cambiar
     * `horas_dia`, `dias_mes` o `minutos_efectivos_hora` reescribe la
     * utilización histórica. Es una decisión explícita (ver README); los
     * costos no se ven afectados porque van congelados en cada cirugía.
     */
    public function minutosDisponiblesEntre(CarbonInterface $inicio, CarbonInterface $fin): int
    {
        if ($fin->lessThan($inicio)) {
            return 0;
        }

        $abarcaMesesCompletos = $inicio->isSameDay($inicio->copy()->startOfMonth())
            && $fin->isSameDay($fin->copy()->endOfMonth());

        if ($abarcaMesesCompletos) {
            // `diffInMonths` devuelve float: de un 1 de junio al 30 de junio
            // 23:59 da 0,999…, y sin truncar el mes se contaba casi dos veces.
            $meses = (int) floor($inicio->diffInMonths($fin)) + 1;

            return $this->minutosDisponiblesMes() * $meses;
        }

        // `diffInDays` es float; se trunca para contar días naturales enteros.
        $dias = (int) $inicio->diffInDays($fin) + 1;

        return (int) round(
            $this->horas_dia * $this->minutos_efectivos_hora * $dias * ($this->dias_mes / 30.4375),
        );
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Paciente, $this> */
    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    /** @return HasMany<Cirugia, $this> */
    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class);
    }

    /** @return HasMany<SalaOperatoria, $this> */
    public function salasOperatorias(): HasMany
    {
        return $this->hasMany(SalaOperatoria::class);
    }

    /** @return HasMany<RecursoHumano, $this> */
    public function recursosHumanos(): HasMany
    {
        return $this->hasMany(RecursoHumano::class);
    }

    /** @return HasMany<ProcedimientoQuirurgico, $this> */
    public function procedimientos(): HasMany
    {
        return $this->hasMany(ProcedimientoQuirurgico::class);
    }

    /** @return HasMany<Insumo, $this> */
    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class);
    }

    /** @return HasMany<EquipoMedico, $this> */
    public function equiposMedicos(): HasMany
    {
        return $this->hasMany(EquipoMedico::class);
    }

    /** @return HasMany<ConceptoCostoIndirecto, $this> */
    public function conceptosCostoIndirecto(): HasMany
    {
        return $this->hasMany(ConceptoCostoIndirecto::class);
    }
}
