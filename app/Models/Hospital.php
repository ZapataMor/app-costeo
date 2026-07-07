<?php

namespace App\Models;

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
 * @property float $factor_indirecto
 */
class Hospital extends Model
{
    /** @use HasFactory<HospitalFactory> */
    use HasFactory;

    protected $table = 'hospitales';

    protected $fillable = [
        'nombre',
        'nit',
        'nivel_complejidad',
        'municipio',
        'departamento',
        'horas_dia',
        'dias_mes',
        'factor_indirecto',
    ];

    protected function casts(): array
    {
        return [
            'horas_dia' => 'integer',
            'dias_mes' => 'integer',
            'factor_indirecto' => 'float',
        ];
    }

    /**
     * Capacidad práctica TDABC: minutos disponibles por recurso al mes.
     * Por defecto 12 h/día × 26 días × 60 = 18.720 minutos.
     */
    public function minutosDisponiblesMes(): int
    {
        return $this->horas_dia * $this->dias_mes * 60;
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
}
