<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\NivelComplejidad;
use App\Enums\NivelConfiabilidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcedimientoQuirurgicoRequest;
use App\Http\Requests\UpdateProcedimientoQuirurgicoRequest;
use App\Models\ProcedimientoQuirurgico;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProcedimientoQuirurgicoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('parametros/procedimientos/index', [
            'procedimientos' => ProcedimientoQuirurgico::orderBy('nombre')->paginate(15)->withQueryString(),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/procedimientos/create', $this->catalogos());
    }

    public function store(StoreProcedimientoQuirurgicoRequest $request): RedirectResponse
    {
        ProcedimientoQuirurgico::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento registrado.']);

        return back();
    }

    public function edit(ProcedimientoQuirurgico $procedimiento): Response
    {
        return Inertia::render('parametros/procedimientos/edit', [
            'procedimiento' => $procedimiento,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateProcedimientoQuirurgicoRequest $request, ProcedimientoQuirurgico $procedimiento): RedirectResponse
    {
        $procedimiento->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento actualizado.']);

        return redirect()->route('parametros.procedimientos.index');
    }

    public function destroy(ProcedimientoQuirurgico $procedimiento): RedirectResponse
    {
        if ($procedimiento->cirugias()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: tiene cirugías registradas asociadas.',
            ]);

            return back();
        }

        $procedimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento eliminado.']);

        return redirect()->route('parametros.procedimientos.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'complejidades' => NivelComplejidad::values(),
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
        ];
    }
}
