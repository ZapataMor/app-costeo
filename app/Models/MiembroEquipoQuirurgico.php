<?php

namespace App\Models;

use App\Enums\FaseCiclo;
use Database\Factories\MiembroEquipoQuirurgicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Participación de un recurso humano en una cirugía (equipo quirúrgico),
 * con el rol desempeñado y los minutos de participación.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $recurso_humano_id
 * @property string $rol
 * @property FaseCiclo $fase
 * @property Carbon|null $hora_inicio
 * @property Carbon|null $hora_fin
 * @property int $minutos_participacion
 * @property string|null $costo_mensual_registrado
 */
class MiembroEquipoQuirurgico extends Model
{
    /** @use HasFactory<MiembroEquipoQuirurgicoFactory> */
    use HasFactory;

    protected $table = 'miembros_equipo_quirurgico';

    protected $fillable = [
        'cirugia_id',
        'recurso_humano_id',
        'rol',
        'fase',
        'hora_inicio',
        'hora_fin',
        'minutos_participacion',
        'costo_mensual_registrado',
    ];

    protected function casts(): array
    {
        return [
            'fase' => FaseCiclo::class,
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
            'minutos_participacion' => 'integer',
            'costo_mensual_registrado' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }

    /** @return BelongsTo<RecursoHumano, $this> */
    public function recursoHumano(): BelongsTo
    {
        return $this->belongsTo(RecursoHumano::class);
    }
}
