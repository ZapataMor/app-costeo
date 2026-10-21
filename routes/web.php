<?php

use App\Http\Controllers\Api\V1\CirugiaController;
use App\Http\Controllers\Api\V1\InsumoController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\PacienteController;
use App\Http\Controllers\Api\V1\ProcedimientoQuirurgicoController;
use App\Http\Controllers\Api\V1\RecursoHumanoController;
use App\Http\Controllers\Cirugias;
use App\Http\Controllers\Conocimiento;
use App\Http\Controllers\Costeo;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCosteoController;
use App\Http\Controllers\DigitadorController;
use App\Http\Controllers\ExportacionController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\HospitalActivoController;
use App\Http\Controllers\Instituciones;
use App\Http\Controllers\Pacientes;
use App\Http\Controllers\Parametros;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    // El digitador entra directo a su único módulo (registro de procedimientos)
    // y el admin_hospital a Parámetros; el dashboard es solo del super_admin.
    $user = auth()->user();

    if ($user->isDigitador()) {
        return redirect()->route('cirugias.index');
    }

    // El profesional entra al repositorio de lecciones: su módulo es la Capa 4.
    if ($user->isProfesional()) {
        return redirect()->route('conocimiento.lecciones.index');
    }

    return $user->isSuperAdmin()
        ? redirect()->route('dashboard')
        : redirect()->route('parametros.index');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Gestión y análisis: super_admin y admin_hospital ────────────────
    // El digitador queda excluido de todo esto (solo registra procedimientos).
    Route::middleware('rol:super_admin,admin_hospital')->group(function () {
        // El dashboard es la vista consolidada multi-hospital: solo el
        // super_admin lo usa, los demás roles entran por Parámetros.
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('rol:super_admin')->name('dashboard');

        // Switcher de hospital del super_admin
        Route::post('hospital-activo', [HospitalActivoController::class, 'store'])->name('hospital-activo.store');

        // ── Instituciones (solo super_admin) ────────────────────────────
        // Alta y administración de hospitales/centros médicos (tenants) y
        // de sus usuarios. Va sin `hospital.contexto`: opera sobre todos.
        Route::prefix('instituciones')->name('instituciones.')
            ->middleware('rol:super_admin')->group(function () {
                Route::get('/', [Instituciones\InstitucionController::class, 'index'])->name('index');
                Route::get('create', [Instituciones\InstitucionController::class, 'create'])->name('create');
                Route::post('/', [Instituciones\InstitucionController::class, 'store'])->name('store');
                Route::get('{hospital}', [Instituciones\InstitucionController::class, 'show'])->name('show');
                Route::get('{hospital}/edit', [Instituciones\InstitucionController::class, 'edit'])->name('edit');
                Route::put('{hospital}', [Instituciones\InstitucionController::class, 'update'])->name('update');
                Route::delete('{hospital}', [Instituciones\InstitucionController::class, 'destroy'])->name('destroy');

                Route::post('{hospital}/usuarios', [Instituciones\UsuarioInstitucionController::class, 'store'])
                    ->name('usuarios.store');
                Route::put('{hospital}/usuarios/{usuario}', [Instituciones\UsuarioInstitucionController::class, 'update'])
                    ->name('usuarios.update');
                Route::patch('{hospital}/usuarios/{usuario}/activo', [Instituciones\UsuarioInstitucionController::class, 'toggleActivo'])
                    ->name('usuarios.toggle-activo');
                Route::delete('{hospital}/usuarios/{usuario}', [Instituciones\UsuarioInstitucionController::class, 'destroy'])
                    ->name('usuarios.destroy');
            });

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
            Route::put('hospital/umbrales', [Parametros\HospitalConfiguracionController::class, 'umbrales'])->name('hospital.umbrales');

            Route::resource('recursos-humanos', Parametros\RecursoHumanoController::class)
                ->except('show')->parameters(['recursos-humanos' => 'recursoHumano']);
            Route::resource('insumos', Parametros\InsumoController::class)->except('show');
            Route::resource('equipos-medicos', Parametros\EquipoMedicoController::class)
                ->except('show')->parameters(['equipos-medicos' => 'equipoMedico']);
            Route::resource('salas-operatorias', Parametros\SalaOperatoriaController::class)
                ->except('show')->parameters(['salas-operatorias' => 'salaOperatoria']);
            Route::resource('procedimientos', Parametros\ProcedimientoQuirurgicoController::class)->except('show');

            // Bolsas de costo indirecto. Encender una categoría excluye del
            // costo directo el componente equivalente y enciende sus bolsas
            // en la misma transacción: las dos rutas van al mismo servicio,
            // que es el único autorizado a mover ese par.
            Route::post('costos-indirectos/activar', [Parametros\ConceptoCostoIndirectoController::class, 'activar'])
                ->name('costos-indirectos.activar');
            Route::post('costos-indirectos/desactivar', [Parametros\ConceptoCostoIndirectoController::class, 'desactivar'])
                ->name('costos-indirectos.desactivar');
            Route::resource('costos-indirectos', Parametros\ConceptoCostoIndirectoController::class)
                ->except('show')->parameters(['costos-indirectos' => 'concepto']);

            // Plantilla del protocolo: lo que el procedimiento usa siempre y
            // con lo que nacerá prellenado cada registro suyo.
            Route::get('procedimientos/{procedimiento}/plantilla', [Parametros\PlantillaProcedimientoController::class, 'edit'])
                ->name('procedimientos.plantilla.edit');
            Route::put('procedimientos/{procedimiento}/plantilla', [Parametros\PlantillaProcedimientoController::class, 'update'])
                ->name('procedimientos.plantilla.update');

            // Deduce la plantilla de lo que ya se registró: arrancar el
            // protocolo de cero es el trabajo que la app existe para ahorrar.
            Route::post('procedimientos/{procedimiento}/plantilla/sugerir', [Parametros\PlantillaProcedimientoController::class, 'sugerir'])
                ->name('procedimientos.plantilla.sugerir');
        });

    // ── Registro de procedimientos (Capa 2) ─────────────────────────────
    // El digitador registra y solo ve/corrige LO QUE ÉL MISMO capturó (gate
    // `corregir-cirugia`); nunca el histórico del hospital, el detalle
    // costeado ni la eliminación, que son de admin_hospital/super_admin.
    Route::prefix('cirugias')->name('cirugias.')->middleware('hospital.contexto')->group(function () {
        Route::get('/', [Cirugias\CirugiaController::class, 'index'])->name('index');
        Route::get('create', [Cirugias\CirugiaController::class, 'create'])->name('create');
        Route::post('/', [Cirugias\CirugiaController::class, 'store'])->name('store');
        Route::post('pacientes', [Cirugias\PacienteController::class, 'store'])->name('pacientes.store');

        // Corrección: sin esto, un error de captura sería permanente y un
        // procedimiento abierto («en proceso», sin hora de fin) jamás
        // podría cerrarse ni entrar al costeo.
        Route::get('{cirugia}/edit', [Cirugias\CirugiaController::class, 'edit'])->name('edit');
        Route::put('{cirugia}', [Cirugias\CirugiaController::class, 'update'])->name('update');
        Route::patch('{cirugia}/cerrar', [Cirugias\CirugiaController::class, 'cerrar'])->name('cerrar');

        Route::middleware('rol:super_admin,admin_hospital')->group(function () {

            Route::get('{cirugia}', [Cirugias\CirugiaController::class, 'show'])->name('show');
            Route::post('{cirugia}/calcular-costo', [Cirugias\CirugiaController::class, 'calcular'])->name('calcular');
            Route::delete('{cirugia}', [Cirugias\CirugiaController::class, 'destroy'])->name('destroy');

            // Cierre del ciclo de datos: sin estas dos capturas, los KPIs de
            // margen, glosas, recaudo y completitud no tienen origen.
            Route::post('{cirugia}/facturacion', [Cirugias\FacturacionController::class, 'store'])
                ->name('facturacion.store');
            Route::post('{cirugia}/resultado-clinico', [Cirugias\ResultadoClinicoController::class, 'store'])
                ->name('resultado-clinico.store');
        });
    });

    // ── Exportaciones CSV ───────────────────────────────────────────────
    // Respetan los filtros de la vista desde la que se descargan.
    Route::prefix('exportar')->name('exportar.')
        ->middleware(['rol:super_admin,admin_hospital', 'hospital.contexto'])->group(function () {
            Route::get('cirugias', [ExportacionController::class, 'cirugias'])->name('cirugias');
            Route::get('indicadores', [ExportacionController::class, 'indicadores'])->name('indicadores');
            Route::get('pendientes', [ExportacionController::class, 'pendientes'])->name('pendientes');
        });

    // ── Pacientes ───────────────────────────────────────────────────────
    // El digitador da de alta pacientes desde el registro (alta rápida);
    // la gestión del padrón es del administrador.
    Route::prefix('pacientes')->name('pacientes.')
        ->middleware(['rol:super_admin,admin_hospital', 'hospital.contexto'])->group(function () {
            Route::get('/', [Pacientes\PacienteController::class, 'index'])->name('index');
            Route::post('/', [Pacientes\PacienteController::class, 'store'])->name('store');
            Route::put('{paciente}', [Pacientes\PacienteController::class, 'update'])->name('update');
            Route::delete('{paciente}', [Pacientes\PacienteController::class, 'destroy'])->name('destroy');
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

            // Costeo por persona: cuánto cuesta y cuánto gasto moviliza cada
            // miembro del equipo quirúrgico, con su histórico de tiempos.
            Route::get('personal', [Costeo\PersonalCosteoController::class, 'index'])
                ->name('personal.index');
            Route::get('personal/{personal}', [Costeo\PersonalCosteoController::class, 'show'])
                ->name('personal.show');

            // Bandeja de sobrecostos: el outlier con estado y con causa, que
            // es lo que cierra el ciclo entre detectar y corregir.
            Route::get('alertas', [Costeo\AlertaSobrecostoController::class, 'index'])
                ->name('alertas.index');
            Route::patch('alertas/{alerta}', [Costeo\AlertaSobrecostoController::class, 'revisar'])
                ->name('alertas.revisar');

            Route::get('componentes', [DashboardCosteoController::class, 'componentes'])->name('componentes');
            Route::get('outliers', [DashboardCosteoController::class, 'outliers'])->name('outliers');
            Route::get('rentabilidad', [DashboardCosteoController::class, 'rentabilidad'])->name('rentabilidad');
            Route::get('variabilidad', [DashboardCosteoController::class, 'variabilidad'])->name('variabilidad');
        });
});

// ── Conocimiento (Capa 4) ───────────────────────────────────────────────
// Reglas, alertas, lecciones aprendidas, recomendaciones, mejores prácticas
// y comunidades de práctica. El profesional (personal quirúrgico) participa
// en lecciones, comunidades y mejores prácticas; la gestión —reglas,
// alertas, recomendaciones, validación de lecciones, cuentas— es del
// administrador del hospital.
Route::middleware(['auth', 'verified', 'hospital.contexto', 'hospital.requerido'])
    ->prefix('conocimiento')->name('conocimiento.')->group(function () {
        Route::middleware('rol:super_admin,admin_hospital,profesional')->group(function () {
            Route::get('lecciones', [Conocimiento\LeccionAprendidaController::class, 'index'])->name('lecciones.index');
            Route::get('lecciones/create', [Conocimiento\LeccionAprendidaController::class, 'create'])->name('lecciones.create');
            Route::post('lecciones', [Conocimiento\LeccionAprendidaController::class, 'store'])->name('lecciones.store');
            Route::get('lecciones/red/{leccion}', [Conocimiento\LeccionAprendidaController::class, 'showRed'])
                ->whereNumber('leccion')->name('lecciones.red');
            Route::get('lecciones/{leccion}', [Conocimiento\LeccionAprendidaController::class, 'show'])->name('lecciones.show');
            Route::get('lecciones/{leccion}/edit', [Conocimiento\LeccionAprendidaController::class, 'edit'])->name('lecciones.edit');
            Route::put('lecciones/{leccion}', [Conocimiento\LeccionAprendidaController::class, 'update'])->name('lecciones.update');
            Route::post('lecciones/{leccion}/enviar', [Conocimiento\LeccionAprendidaController::class, 'enviar'])->name('lecciones.enviar');
            Route::post('lecciones/{leccion}/valorar', [Conocimiento\LeccionAprendidaController::class, 'valorar'])->name('lecciones.valorar');

            Route::get('mejores-practicas', [Conocimiento\MejoresPracticasController::class, 'index'])->name('mejores-practicas.index');
            Route::get('mejores-practicas/{procedimiento}', [Conocimiento\MejoresPracticasController::class, 'show'])->name('mejores-practicas.show');

            Route::get('comunidades', [Conocimiento\ComunidadController::class, 'index'])->name('comunidades.index');
            Route::get('comunidades/{comunidad}', [Conocimiento\ComunidadController::class, 'show'])->name('comunidades.show');
            Route::post('comunidades/{comunidad}/hilos', [Conocimiento\ComunidadController::class, 'storeHilo'])->name('comunidades.hilos.store');
            Route::get('hilos/{hilo}', [Conocimiento\HiloComunidadController::class, 'show'])->name('hilos.show');
            Route::post('hilos/{hilo}/respuestas', [Conocimiento\HiloComunidadController::class, 'responder'])->name('hilos.responder');
            Route::patch('hilos/{hilo}/respuestas/{respuesta}/solucion', [Conocimiento\HiloComunidadController::class, 'marcarSolucion'])->name('hilos.solucion');
            Route::patch('hilos/{hilo}', [Conocimiento\HiloComunidadController::class, 'moderar'])->name('hilos.moderar');
        });

        Route::middleware('rol:super_admin,admin_hospital')->group(function () {
            Route::get('/', [Conocimiento\TableroConocimientoController::class, 'index'])->name('index');

            Route::get('reglas', [Conocimiento\ReglaConocimientoController::class, 'index'])->name('reglas.index');
            Route::put('reglas/{regla}', [Conocimiento\ReglaConocimientoController::class, 'update'])->name('reglas.update');
            Route::post('reglas/evaluar', [Conocimiento\ReglaConocimientoController::class, 'evaluar'])->name('reglas.evaluar');

            Route::get('alertas', [Conocimiento\AlertaConocimientoController::class, 'index'])->name('alertas.index');
            Route::patch('alertas/{alerta}', [Conocimiento\AlertaConocimientoController::class, 'revisar'])->name('alertas.revisar');

            Route::get('recomendaciones', [Conocimiento\RecomendacionController::class, 'index'])->name('recomendaciones.index');
            Route::post('recomendaciones/generar', [Conocimiento\RecomendacionController::class, 'generar'])->name('recomendaciones.generar');
            Route::patch('recomendaciones/{recomendacion}', [Conocimiento\RecomendacionController::class, 'gestionar'])->name('recomendaciones.gestionar');

            Route::post('lecciones/{leccion}/publicar', [Conocimiento\LeccionAprendidaController::class, 'publicar'])->name('lecciones.publicar');
            Route::post('lecciones/{leccion}/devolver', [Conocimiento\LeccionAprendidaController::class, 'devolver'])->name('lecciones.devolver');
            Route::post('lecciones/{leccion}/archivar', [Conocimiento\LeccionAprendidaController::class, 'archivar'])->name('lecciones.archivar');
            Route::post('lecciones/{leccion}/compartir', [Conocimiento\LeccionAprendidaController::class, 'compartir'])->name('lecciones.compartir');

            Route::get('profesionales', [Conocimiento\ProfesionalController::class, 'index'])->name('profesionales.index');
            Route::get('profesionales/create', [Conocimiento\ProfesionalController::class, 'create'])->name('profesionales.create');
            Route::post('profesionales', [Conocimiento\ProfesionalController::class, 'store'])->name('profesionales.store');
            Route::patch('profesionales/{usuario}/activo', [Conocimiento\ProfesionalController::class, 'toggleActivo'])->name('profesionales.toggle-activo');
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
