<?php

use App\Http\Controllers\Api\V1\CirugiaController;
use App\Http\Controllers\Api\V1\InsumoController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\PacienteController;
use App\Http\Controllers\Api\V1\ProcedimientoQuirurgicoController;
use App\Http\Controllers\Api\V1\RecursoHumanoController;
use App\Http\Controllers\Cirugias;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCosteoController;
use App\Http\Controllers\HospitalActivoController;
use App\Http\Controllers\Parametros;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Switcher de hospital del super_admin
    Route::post('hospital-activo', [HospitalActivoController::class, 'store'])->name('hospital-activo.store');

    // ── Parámetros del hospital (Capa 1) ────────────────────────────────
    Route::prefix('parametros')->name('parametros.')->middleware('hospital.contexto')->group(function () {
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

    // ── Cirugías y costeo TDABC (Capa 2) ────────────────────────────────
    Route::prefix('cirugias')->name('cirugias.')->middleware('hospital.contexto')->group(function () {
        Route::get('/', [Cirugias\CirugiaController::class, 'index'])->name('index');
        Route::get('create', [Cirugias\CirugiaController::class, 'create'])->name('create');
        Route::post('/', [Cirugias\CirugiaController::class, 'store'])->name('store');
        Route::post('pacientes', [Cirugias\PacienteController::class, 'store'])->name('pacientes.store');
        Route::get('{cirugia}', [Cirugias\CirugiaController::class, 'show'])->name('show');
        Route::post('{cirugia}/calcular-costo', [Cirugias\CirugiaController::class, 'calcular'])->name('calcular');
    });

    // ── Dashboards de costeo (Capa 3c) ──────────────────────────────────
    Route::prefix('costeo')->name('costeo.')->middleware('hospital.contexto')->group(function () {
        Route::get('/', [DashboardCosteoController::class, 'index'])->name('index');
        Route::get('componentes', [DashboardCosteoController::class, 'componentes'])->name('componentes');
        Route::get('outliers', [DashboardCosteoController::class, 'outliers'])->name('outliers');
        Route::get('rentabilidad', [DashboardCosteoController::class, 'rentabilidad'])->name('rentabilidad');
        Route::get('variabilidad', [DashboardCosteoController::class, 'variabilidad'])->name('variabilidad');
    });
});

// ── API v1 (sesión web; consumida por los dashboards y tests) ───────────
Route::middleware(['auth', 'hospital.contexto'])->prefix('api/v1')->name('api.v1.')->group(function () {
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
