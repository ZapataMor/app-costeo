<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paciente. El documento de identidad se guarda cifrado (Ley 1581/2012)
 * y se busca por documento_hash (SHA-256).
 *
 * @property int $id
 * @property int $hospital_id
 * @property string $tipo_documento
 * @property string $documento
 * @property string $documento_hash
 * @property string $nombres
 * @property string $apellidos
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $sexo
 * @property string $regimen
 * @property string|null $asegurador
 * @property string $zona
 * @property string|null $municipio
 */
class Paciente extends Model
{
    /** @use HasFactory<PacienteFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'pacientes';

    protected $fillable = [
        'hospital_id',
        'tipo_documento',
        'documento',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'sexo',
        'regimen',
        'asegurador',
        'zona',
        'municipio',
    ];

    protected $hidden = ['documento'];

    protected function casts(): array
    {
        return [
            'documento' => 'encrypted',
            'fecha_nacimiento' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Paciente $paciente): void {
            if ($paciente->isDirty('documento')) {
                $paciente->documento_hash = static::hashDocumento($paciente->documento);
            }
        });
    }

    public static function hashDocumento(string $documento): string
    {
        return hash('sha256', $documento);
    }

    /** @return HasMany<Cirugia, $this> */
    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class);
    }
}
