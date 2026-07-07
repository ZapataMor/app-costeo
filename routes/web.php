<?php

use App\Http\Controllers\Api\V1\CirugiaController;
use App\Http\Controllers\Api\V1\InsumoController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\PacienteController;
use App\Http\Controllers\Api\V1\ProcedimientoQuirurgicoController;
use App\Http\Controllers\Api\V1\RecursoHumanoController;
use App\Http\Controllers\DashboardCosteoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // ── Dashboards de costeo (Capa 3c) ──────────────────────────────────
    Route::prefix('costeo')->name('costeo.')->group(function () {
        Route::get('/', [DashboardCosteoController::class, 'index'])->name('index');
        Route::get('componentes', [DashboardCosteoController::class, 'componentes'])->name('componentes');
        Route::get('outliers', [DashboardCosteoController::class, 'outliers'])->name('outliers');
        Route::get('rentabilidad', [DashboardCosteoController::class, 'rentabilidad'])->name('rentabilidad');
        Route::get('variabilidad', [DashboardCosteoController::class, 'variabilidad'])->name('variabilidad');
    });
});

// ── API v1 (sesión web; consumida por los dashboards y tests) ───────────
Route::middleware(['auth'])->prefix('api/v1')->name('api.v1.')->group(function () {
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
