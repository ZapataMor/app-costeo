<?php

use App\Http\Controllers\Api\V1\CirugiaController;
use App\Http\Controllers\Api\V1\InsumoController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\PacienteController;
use App\Http\Controllers\Api\V1\ProcedimientoQuirurgicoController;
use App\Http\Controllers\Api\V1\RecursoHumanoController;
use App\Http\Controllers\Cirugias;
use App\Http\Controllers\Costeo;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCosteoController;
use App\Http\Controllers\DigitadorController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\HospitalActivoController;
use App\Http\Controllers\Parametros;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    // El digitador entra directo a su único módulo (registro de procedimientos).
    return auth()->user()->isDigitador()
        ? redirect()->route('cirugias.index')
        : redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Gestión y análisis: super_admin y admin_hospital ────────────────
    // El digitador queda excluido de todo esto (solo registra procedimientos).
    Route::middleware('rol:super_admin,admin_hospital')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Switcher de hospital del super_admin
        Route::post('hospital-activo', [HospitalActivoController::class, 'store'])->name('hospital-activo.store');

        // Historial de actividad (auditoría)
        Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');

        // Gestión de digitadores (solo el administrador de cada hospital)
        Route::middleware(['rol:admin_hospital', 'hospital.contexto'])->group(function () {
            Route::get('digitadores', [DigitadorController::class, 'index'])->name('digitadores.index');
            Route::get('digitadores/create', [DigitadorController::class, 'create'])->name('digitadores.create');
            Route::post('digitadores', [DigitadorController::class, 'store'])->name('digitadores.store');
            Route::patch('digitadores/{usuario}/activo', [DigitadorController::class, 'toggleActivo'])
                ->name('digitadores.toggle-activo');
        });
    });

    // ── Parámetros del hospital (Capa 1) ────────────────────────────────
    Route::prefix('parametros')->name('parametros.')
        ->middleware(['rol:super_admin,admin_hospital', 'hospital.contexto'])->group(function () {
            Route::get('/', [Parametros\ParametrosController::class, 'index'])->name('index');
            Route::get('hospital', [Parametros\HospitalConfiguracionController::class, 'edit'])->name('hospital.edit');
            Route::put('hospital', [Parametros\HospitalConfiguracionController::class, 'update'])->name('hospital.update');

            Route::resource('recursos-humanos', Parametros\RecursoHumanoController::class)
                ->except('show')->parameters(['recursos-humanos' => 'recursoHumano']);
            Route::resource('insumos', Parametros\InsumoController::class)->except('show');
            Route::resource('equipos-medicos', Parametros\EquipoMedicoController::class)
                ->except('show')->parameters(['equipos-medicos' => 'equipoMedico']);
            Route::resource('salas-operatorias', Parametros\SalaOperatoriaController::class)
                ->except('show')->parameters(['salas-operatorias' => 'salaOperatoria']);
            Route::resource('procedimientos', Parametros\ProcedimientoQuirurgicoController::class)->except('show');
        });

    // ── Registro de procedimientos (Capa 2) ─────────────────────────────
    // El digitador solo accede al registro (index/create/store/pacientes);
    // el detalle y el costeo manual quedan para admin_hospital/super_admin.
    Route::prefix('cirugias')->name('cirugias.')->middleware('hospital.contexto')->group(function () {
        Route::get('/', [Cirugias\CirugiaController::class, 'index'])->name('index');
        Route::get('create', [Cirugias\CirugiaController::class, 'create'])->name('create');
        Route::post('/', [Cirugias\CirugiaController::class, 'store'])->name('store');
        Route::post('pacientes', [Cirugias\PacienteController::class, 'store'])->name('pacientes.store');

        Route::middleware('rol:super_admin,admin_hospital')->group(function () {
            Route::get('{cirugia}', [Cirugias\CirugiaController::class, 'show'])->name('show');
            Route::post('{cirugia}/calcular-costo', [Cirugias\CirugiaController::class, 'calcular'])->name('calcular');
        });
    });

    // ── Dashboards de costeo (Capa 3c) ──────────────────────────────────
    Route::prefix('costeo')->name('costeo.')
        ->middleware(['rol:super_admin,admin_hospital', 'hospital.contexto'])->group(function () {
            Route::get('/', [DashboardCosteoController::class, 'index'])->name('index');

            // Explorador de procedimientos: catálogo → cirugías → detalle costeado
            Route::get('procedimientos', [Costeo\ProcedimientoCosteoController::class, 'index'])
                ->name('procedimientos.index');
            Route::get('procedimientos/{procedimiento}', [Costeo\ProcedimientoCosteoController::class, 'show'])
                ->name('procedimientos.show');
            Route::get('procedimientos/{procedimiento}/cirugias/{cirugia}', [Costeo\ProcedimientoCosteoController::class, 'cirugia'])
                ->name('procedimientos.cirugia');

            Route::get('componentes', [DashboardCosteoController::class, 'componentes'])->name('componentes');
            Route::get('outliers', [DashboardCosteoController::class, 'outliers'])->name('outliers');
            Route::get('rentabilidad', [DashboardCosteoController::class, 'rentabilidad'])->name('rentabilidad');
            Route::get('variabilidad', [DashboardCosteoController::class, 'variabilidad'])->name('variabilidad');
        });
});

// ── API v1 (sesión web; consumida por los dashboards y tests) ───────────
Route::middleware(['auth', 'rol:super_admin,admin_hospital', 'hospital.contexto'])
    ->prefix('api/v1')->name('api.v1.')->group(function () {
        // KPIs (Capa 3b — Donabedian)
        Route::get('kpis/costos', [KpiController::class, 'costos'])->name('kpis.costos');
        Route::get('kpis/variabilidad', [KpiController::class, 'variabilidad'])->name('kpis.variabilidad');
        Route::get('kpis/margen', [KpiController::class, 'margen'])->name('kpis.margen');
        Route::get('kpis/utilizacion-salas', [KpiController::class, 'utilizacionSalas'])->name('kpis.utilizacion-salas');
        Route::get('kpis/glosas-recaudo', [KpiController::class, 'glosasRecaudo'])->name('kpis.glosas-recaudo');
        Route::get('kpis/completitud', [KpiController::class, 'completitud'])->name('kpis.completitud');
        Route::get('kpis/outliers', [KpiController::class, 'outliers'])->name('kpis.outliers');
        Route::get('kpis/componentes', [KpiController::class, 'componentes'])->name('kpis.componentes');

        // Captura de datos (Capa 2)
        Route::post('pacientes', [PacienteController::class, 'store'])->name('pacientes.store');
        Route::post('insumos', [InsumoController::class, 'store'])->name('insumos.store');
        Route::post('procedimientos', [ProcedimientoQuirurgicoController::class, 'store'])->name('procedimientos.store');
        Route::post('recursos-humanos', [RecursoHumanoController::class, 'store'])->name('recursos-humanos.store');
        Route::post('cirugias', [CirugiaController::class, 'store'])->name('cirugias.store');
        Route::post('cirugias/{cirugia}/calcular-costo', [CirugiaController::class, 'calcularCosto'])->name('cirugias.calcular-costo');
    });

require __DIR__.'/settings.php';
