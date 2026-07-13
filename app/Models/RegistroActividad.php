<?php

namespace App\Models;

use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Bitácora de auditoría: quién hizo qué y cuándo. Registra inicios de
 * sesión, cambios de contexto y toda mutación de datos del dominio.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $hospital_id
 * @property string $accion
 * @property string $descripcion
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 */
class RegistroActividad extends Model
{
    protected $table = 'registros_actividad';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'hospital_id',
        'accion',
        'descripcion',
        'auditable_type',
        'auditable_id',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Registra una acción en la bitácora con el usuario autenticado y el
     * hospital del contexto actual (o el indicado).
     */
    public static function registrar(
        string $accion,
        string $descripcion,
        ?Model $auditable = null,
        ?int $hospitalId = null,
        ?User $usuario = null,
    ): self {
        /** @var User|null $user */
        $user = $usuario ?? Auth::user();

        return self::query()->create([
            'user_id' => $user?->id,
            'hospital_id' => $hospitalId
                ?? $auditable?->getAttribute('hospital_id')
                ?? HospitalContext::id()
                ?? $user?->hospital_id,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
