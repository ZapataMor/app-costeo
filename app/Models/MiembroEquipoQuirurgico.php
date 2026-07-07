<?php

namespace App\Models;

use Database\Factories\MiembroEquipoQuirurgicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participación de un recurso humano en una cirugía (equipo quirúrgico),
 * con el rol desempeñado y los minutos de participación.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $recurso_humano_id
 * @property string $rol
 * @property int $minutos_participacion
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
        'minutos_participacion',
    ];

    protected function casts(): array
    {
        return [
            'minutos_participacion' => 'integer',
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
