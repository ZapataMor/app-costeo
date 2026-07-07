<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Database\Factories\ResultadoClinicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado clínico de la cirugía (dimensión "resultado" de Donabedian).
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $hospital_id
 * @property bool $complicacion_intraoperatoria
 * @property bool $complicacion_postoperatoria
 * @property int $dias_estancia
 * @property bool $reingreso_30_dias
 * @property bool $mortalidad
 */
class ResultadoClinico extends Model
{
    /** @use HasFactory<ResultadoClinicoFactory> */
    use BelongsToHospital, HasFactory;

    protected $table = 'resultados_clinicos';

    protected $fillable = [
        'cirugia_id',
        'hospital_id',
        'complicacion_intraoperatoria',
        'descripcion_complicacion_intra',
        'complicacion_postoperatoria',
        'descripcion_complicacion_post',
        'dias_estancia',
        'reingreso_30_dias',
        'mortalidad',
    ];

    protected function casts(): array
    {
        return [
            'complicacion_intraoperatoria' => 'boolean',
            'complicacion_postoperatoria' => 'boolean',
            'dias_estancia' => 'integer',
            'reingreso_30_dias' => 'boolean',
            'mortalidad' => 'boolean',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }
}
