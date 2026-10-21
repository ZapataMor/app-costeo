<?php

namespace App\Services\Conocimiento;

use App\Enums\TipoReglaConocimiento;
use App\Models\Hospital;
use App\Models\ReglaConocimiento;
use App\Models\Scopes\HospitalScope;

/**
 * Garantiza que cada hospital tenga sus reglas de conocimiento.
 *
 * Se crean perezosamente, con los parámetros por defecto del catálogo, la
 * primera vez que se necesitan: un hospital nuevo empieza vigilado sin que
 * nadie tenga que configurar nada, y solo ajusta lo que no le sirva.
 */
class CatalogoReglas
{
    /** @var array<int, array<string, ReglaConocimiento>> */
    private array $cache = [];

    /**
     * Reglas del hospital indexadas por tipo.
     *
     * @return array<string, ReglaConocimiento>
     */
    public function delHospital(Hospital|int $hospital): array
    {
        $hospitalId = $hospital instanceof Hospital ? $hospital->id : $hospital;

        if (isset($this->cache[$hospitalId])) {
            return $this->cache[$hospitalId];
        }

        $existentes = ReglaConocimiento::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospitalId)
            ->get()
            ->keyBy(fn (ReglaConocimiento $r): string => $r->tipo->value)
            ->all();

        foreach (TipoReglaConocimiento::cases() as $tipo) {
            if (isset($existentes[$tipo->value])) {
                continue;
            }

            $existentes[$tipo->value] = ReglaConocimiento::query()->withoutGlobalScope(HospitalScope::class)->create([
                'hospital_id' => $hospitalId,
                'tipo' => $tipo,
                'nombre' => $tipo->nombre(),
                'descripcion' => $tipo->descripcion(),
                'severidad' => $tipo->severidadPorDefecto(),
                'parametros' => array_map(fn (array $p): float|int => $p['valor'], $tipo->parametrosPorDefecto()),
                'activa' => true,
            ]);
        }

        return $this->cache[$hospitalId] = $existentes;
    }

    /** Olvida la caché (tras editar una regla en la misma petición). */
    public function olvidar(): void
    {
        $this->cache = [];
    }
}
