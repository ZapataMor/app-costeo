<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\FacturacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Facturación de la cirugía: base de los KPIs de margen, glosas y recaudo.
 *
 * @property int $id
 * @property int $cirugia_id
 * @property int $hospital_id
 * @property string $valor_facturado
 * @property string $valor_glosado
 * @property string $valor_recaudado
 * @property string|null $tarifa_referencia_soat
 * @property Carbon|null $fecha_facturacion
 */
class Facturacion extends Model
{
    /** @use HasFactory<FacturacionFactory> */
    use Auditable, BelongsToHospital, HasFactory;

    protected $table = 'facturaciones';

    protected $fillable = [
        'cirugia_id',
        'hospital_id',
        'valor_facturado',
        'valor_glosado',
        'valor_recaudado',
        'tarifa_referencia_soat',
        'fecha_facturacion',
    ];

    protected function casts(): array
    {
        return [
            'valor_facturado' => 'decimal:2',
            'valor_glosado' => 'decimal:2',
            'valor_recaudado' => 'decimal:2',
            'tarifa_referencia_soat' => 'decimal:2',
            'fecha_facturacion' => 'date',
        ];
    }

    /** @return BelongsTo<Cirugia, $this> */
    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class);
    }
}
