<?php

namespace App\Http\Controllers\Pacientes;

use App\Enums\Regimen;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePacienteRequest;
use App\Http\Requests\UpdatePacienteRequest;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo de pacientes del hospital activo.
 *
 * El documento está cifrado en reposo, así que no se puede buscar con LIKE:
 * un término que parece un documento se busca por su HMAC (coincidencia
 * exacta) y cualquier otro por nombres y apellidos.
 */
class PacienteController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->string('q'));

        $pacientes = Paciente::query()
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $query) use ($busqueda): void {
                    $query
                        ->where('nombres', 'like', "%{$busqueda}%")
                        ->orWhere('apellidos', 'like', "%{$busqueda}%")
                        ->orWhere('documento_hash', Paciente::hashDocumento($busqueda));
                });
            })
            ->withCount('cirugias')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Paciente $paciente): array => [
                'id' => $paciente->id,
                'tipo_documento' => $paciente->tipo_documento,
                // El documento se descifra solo para mostrarlo a quien ya
                // tiene acceso al hospital; nunca sale en listados públicos.
                'documento' => $paciente->documento,
                'nombres' => $paciente->nombres,
                'apellidos' => $paciente->apellidos,
                'fecha_nacimiento' => $paciente->fecha_nacimiento?->toDateString(),
                'sexo' => $paciente->sexo,
                'regimen' => $paciente->regimen,
                'asegurador' => $paciente->asegurador,
                'zona' => $paciente->zona,
                'municipio' => $paciente->municipio,
                'cirugias_count' => $paciente->cirugias_count,
            ]);

        return Inertia::render('pacientes/index', [
            'pacientes' => $pacientes,
            'filtros' => ['q' => $busqueda],
            'regimenes' => Regimen::values(),
        ]);
    }

    public function store(StorePacienteRequest $request): RedirectResponse
    {
        $paciente = Paciente::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Paciente {$paciente->nombres} {$paciente->apellidos} registrado.",
        ]);

        return back();
    }

    public function update(UpdatePacienteRequest $request, Paciente $paciente): RedirectResponse
    {
        $paciente->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente actualizado.']);

        return back();
    }

    public function destroy(Paciente $paciente): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        // Un paciente con procedimientos es la referencia de esos costos:
        // borrarlo dejaría el histórico sin sujeto.
        if ($paciente->cirugias()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar un paciente con procedimientos registrados.',
            ]);

            return back();
        }

        $paciente->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente eliminado.']);

        return back();
    }
}
