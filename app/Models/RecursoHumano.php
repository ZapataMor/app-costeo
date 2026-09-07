<?php

namespace App\Models;

use App\Enums\NivelConfiabilidad;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\RecursoHumanoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona/rol del equipo quirúrgico con su estructura salarial,
 * base del costo por minuto del modelo TDABC.
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $nombre
 * @property string $rol
 * @property string|null $especialidad
 * @property string $salario_mensual
 * @property string $prestaciones_mensuales
 * @property string $costos_indirectos_mensuales
 * @property bool $activo
 * @property string|null $fuente
 * @property NivelConfiabilidad $nivel_confiabilidad
 */
class RecursoHumano extends Model
{
    /** @use HasFactory<RecursoHumanoFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'recursos_humanos';

    protected $fillable = [
        'hospital_id',
        'nombre',
        'rol',
        'especialidad',
        'salario_mensual',
        'prestaciones_mensuales',
        'costos_indirectos_mensuales',
        'activo',
        'fuente',
        'nivel_confiabilidad',
    ];

    protected function casts(): array
    {
        return [
            'salario_mensual' => 'decimal:2',
            'prestaciones_mensuales' => 'decimal:2',
            'costos_indirectos_mensuales' => 'decimal:2',
            'activo' => 'boolean',
            'nivel_confiabilidad' => NivelConfiabilidad::class,
        ];
    }

    /**
     * salario + prestaciones + indirectos asignados.
     *
     * Los indirectos se excluyen cuando el hospital marcó
     * `origen_personal_indirecto = derivado_de_cif`: ese costo ya lo cubre la
     * bolsa CIF de personal indirecto y sumarlo aquí lo contaría dos veces.
     * El campo digitado se conserva intacto —hace falta para comparar el
     * método actual con el método por bolsas— y es el costo congelado en cada
     * cirugía el que refleja la decisión vigente al registrarla.
     */
    public function costoMensualTotal(bool $incluirIndirectos = true): float
    {
        return (float) $this->salario_mensual
            + (float) $this->prestaciones_mensuales
            + ($incluirIndirectos ? (float) $this->costos_indirectos_mensuales : 0.0);
    }

    /**
     * costo/minuto = costo mensual total ÷ minutos disponibles/mes.
     * Si no se pasan los minutos, se toman del hospital del recurso.
     */
    public function costoPorMinuto(?int $minutosDisponibles = null): float
    {
        $minutosDisponibles ??= $this->hospital->minutosDisponiblesMes();

        return $this->costoMensualTotal() / $minutosDisponibles;
    }

    /** @return HasMany<MiembroEquipoQuirurgico, $this> */
    public function participaciones(): HasMany
    {
        return $this->hasMany(MiembroEquipoQuirurgico::class);
    }
}
