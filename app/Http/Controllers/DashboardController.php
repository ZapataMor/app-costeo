<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\Scopes\HospitalScope;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard de entrada: para el super_admin muestra una card por cada
 * centro clínico/hospitalario (más la vista consolidada "Todos"); para
 * un admin_hospital muestra solo el resumen de su hospital.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $hospitales = Hospital::query()
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereKey($user->hospital_id))
            ->orderBy('nombre')
            ->get();

        $cirugias = Cirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->selectRaw('hospital_id, count(*) as total')
            ->groupBy('hospital_id')->pluck('total', 'hospital_id');

        $pacientes = Paciente::query()->withoutGlobalScope(HospitalScope::class)
            ->selectRaw('hospital_id, count(*) as total')
            ->groupBy('hospital_id')->pluck('total', 'hospital_id');

        $costos = CostoCirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->selectRaw('hospital_id, sum(costo_total) as total')
            ->groupBy('hospital_id')->pluck('total', 'hospital_id');

        return Inertia::render('dashboard', [
            'hospitales' => $hospitales->map(fn (Hospital $h): array => [
                'id' => $h->id,
                'nombre' => $h->nombre,
                'municipio' => $h->municipio,
                'departamento' => $h->departamento,
                'nivel_complejidad' => $h->nivel_complejidad,
                'cirugias' => (int) ($cirugias[$h->id] ?? 0),
                'pacientes' => (int) ($pacientes[$h->id] ?? 0),
                'costo_total' => (float) ($costos[$h->id] ?? 0),
            ])->values()->all(),
        ]);
    }
}
