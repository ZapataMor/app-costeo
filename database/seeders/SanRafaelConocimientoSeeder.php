<?php

namespace Database\Seeders;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Enums\EstadoLeccion;
use App\Enums\EstadoRecomendacion;
use App\Enums\RolUsuario;
use App\Models\AlertaConocimiento;
use App\Models\AlertaSobrecosto;
use App\Models\ComunidadPractica;
use App\Models\HiloComunidad;
use App\Models\Hospital;
use App\Models\LeccionAprendida;
use App\Models\ProcedimientoQuirurgico;
use App\Models\Recomendacion;
use App\Models\RecursoHumano;
use App\Models\User;
use App\Services\Conocimiento\GeneradorRecomendaciones;
use App\Services\Conocimiento\GestorComunidades;
use App\Services\Conocimiento\MotorReglas;
use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escenario de prueba de la Capa 4 sobre el movimiento de San Rafael.
 *
 * Simula seis meses de uso del módulo de conocimiento:
 *   - 12 cuentas de profesionales atadas a su recurso humano;
 *   - revisión del ~70 % de las alertas de sobrecosto con su causa (la
 *     complicación clínica donde la hubo, la causa típica del componente
 *     dominante en el resto) y del ~45 % de las alertas de las reglas;
 *   - 22 lecciones en todos los estados, 5 compartidas con la red, y 2
 *     lecciones del Hospital de Riohacha publicadas en la red;
 *   - comunidades por especialidad con hilos, respuestas y soluciones;
 *   - recomendaciones generadas, algunas aceptadas, implementadas o
 *     descartadas.
 *
 *   php artisan db:seed --class=SanRafaelConocimientoSeeder
 *
 * Requiere SanRafaelCargaSeeder y SanRafaelMovimientoSeeder. No acumulativo:
 * si ya hay lecciones de la carga, no hace nada. Ficticio [SEMILLA-CARGA].
 */
class SanRafaelConocimientoSeeder extends Seeder
{
    use InformaEnConsola;

    private const SEMILLA_ALEATORIA = 20_261_020;

    private Hospital $hospital;

    private User $admin;

    /** @var array<string, User> */
    private array $profesionales = [];

    /** @var list<array{string, string}> */
    private array $claves = [];

    public function run(MotorReglas $motor, GeneradorRecomendaciones $generador, GestorComunidades $comunidades): void
    {
        $hospital = Hospital::query()->where('nit', SanRafaelCargaSeeder::NIT)->first();
        $admin = User::query()->where('email', 'admin@sanrafael.test')->first();

        if ($hospital === null || $admin === null) {
            $this->advertir('Faltan el Hospital San Rafael o su administrador: ejecuta DemoSeeder y las cargas de San Rafael primero.');

            return;
        }

        $this->hospital = $hospital;
        $this->admin = $admin;
        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            if (LeccionAprendida::query()->where('etiquetas', 'like', '%semilla-carga%')->exists()) {
                $this->advertir('San Rafael ya tiene el escenario de conocimiento: no se siembra de nuevo.');

                return;
            }

            mt_srand(self::SEMILLA_ALEATORIA);
            /** @var array{profesionales: array<string, array{0: string, 1: string}>, lecciones: list<array<string, mixed>>, red: list<array<string, mixed>>, hilos: list<array<string, mixed>>} $datos */
            $datos = require database_path('seeders/datos/san-rafael/conocimiento.php');

            DB::transaction(function () use ($datos, $motor, $comunidades): void {
                $this->crearProfesionales($datos['profesionales']);
                $this->revisarSobrecostos();
                $motor->evaluarHistorico($this->hospital);
                $this->revisarAlertasDeReglas();
                $lecciones = $this->crearLecciones($datos['lecciones']);
                $this->crearLeccionesDeLaRed($datos['red']);
                $comunidades->asegurar($this->hospital);
                $this->crearHilos($datos['hilos'], $lecciones);
            });

            $generador->generar($this->hospital);
            $this->gestionarRecomendaciones();
            $this->resumir();
        } finally {
            HospitalContext::set($anterior);
        }
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $definiciones
     */
    protected function crearProfesionales(array $definiciones): void
    {
        foreach ($definiciones as $clave => [$nombreRecurso, $email]) {
            $recurso = RecursoHumano::query()->where('nombre', $nombreRecurso)->first();

            if ($recurso === null) {
                continue;
            }

            $usuario = User::query()->firstOrNew(['email' => $email]);
            if (! $usuario->exists) {
                $contrasena = filled(config('seeding.user_password')) ? (string) config('seeding.user_password') : Str::password(20);
                $usuario->password = $contrasena;
                $this->claves[] = [$email, $contrasena];
            }

            $usuario->forceFill([
                'name' => preg_replace('/^(Dra?|Inst|Enf|Aux)\.\s*/', '', $nombreRecurso),
                'role' => RolUsuario::Profesional->value,
                'hospital_id' => $this->hospital->id,
                'recurso_humano_id' => $recurso->id,
                'activo' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->profesionales[$clave] = $usuario;
        }
    }

    /**
     * Revisa el ~70 % de las alertas de sobrecosto, con la causa que un
     * jefe de quirófanos atribuiría: complicación si la cirugía la tuvo, la
     * causa típica del componente dominante si no, y descarte de un 8 %.
     */
    protected function revisarSobrecostos(): void
    {
        $alertas = AlertaSobrecosto::query()->with('cirugia')->orderBy('id')->get();

        foreach ($alertas as $alerta) {
            if (mt_rand(1, 100) > 70) {
                continue;
            }

            $descartar = mt_rand(1, 100) <= 8;
            $complicada = str_contains((string) $alerta->cirugia?->observaciones, 'Complicación');
            $probables = $alerta->componente_dominante->causasProbables();
            $causa = $complicada ? CausaSobrecosto::ComplicacionClinica : $probables[mt_rand(0, count($probables) - 1)];

            $alerta->update([
                'estado' => $descartar ? EstadoAlerta::Descartada : EstadoAlerta::Revisada,
                'causa' => $descartar ? null : $causa,
                'causa_detalle' => $descartar ? 'Baseline aún pobre para este procedimiento.' : $this->detalleCausa($causa),
                'revisado_por' => $this->admin->id,
                'revisado_en' => $alerta->detectado_en->copy()->addDays(mt_rand(1, 6)),
            ]);
        }
    }

    protected function detalleCausa(CausaSobrecosto $causa): ?string
    {
        return match ($causa) {
            CausaSobrecosto::ComplicacionClinica => 'Sangrado mayor al esperado; requirió hemostasia adicional.',
            CausaSobrecosto::ConsumoExcesivoInsumos => 'Se abrieron insumos que no se usaron.',
            CausaSobrecosto::TiempoQuirurgicoProlongado => 'Anatomía difícil; el tiempo quirúrgico se prolongó.',
            CausaSobrecosto::RetrasoAlistamiento => 'La sala esperó ropa estéril de la central.',
            CausaSobrecosto::ErrorDeRegistro => 'Cantidad mal digitada; se corrigió el registro.',
            default => null,
        };
    }

    /** Revisa casi la mitad de las alertas de las reglas, las más antiguas. */
    protected function revisarAlertasDeReglas(): void
    {
        $comentarios = [
            'desviacion_estandar' => 'Revisado con el equipo: caso complejo, se documentó en la comunidad.',
            'tiempo_prolongado' => 'Retraso en la inducción y en el recambio; se habló con anestesia.',
            'consumo_fuera_protocolo' => 'Insumo necesario por hallazgo intraoperatorio.',
            'margen_negativo' => 'Se lleva a la mesa de negociación con la EPS.',
            'sala_subutilizada' => 'Se revisa la programación por especialidad.',
            'tasa_complicaciones' => 'Revisado en el comité de calidad; casos sin patrón común.',
            'tasa_cancelacion' => 'Cancelaciones por ayuno incompleto; se reforzó la llamada previa.',
            'completitud_baja' => 'Se pidió a facturación cerrar los pendientes del mes.',
        ];

        $alertas = AlertaConocimiento::query()->orderBy('created_at')->orderBy('id')->get();

        foreach ($alertas as $alerta) {
            if (mt_rand(1, 100) > 45) {
                continue;
            }

            $alerta->update([
                'estado' => mt_rand(1, 100) <= 12 ? EstadoAlerta::Descartada : EstadoAlerta::Revisada,
                'comentario' => $comentarios[$alerta->tipo->value],
                'revisado_por' => $this->admin->id,
                'revisado_en' => now()->subDays(mt_rand(1, 60)),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return list<LeccionAprendida>
     */
    protected function crearLecciones(array $definiciones): array
    {
        $procedimientos = ProcedimientoQuirurgico::query()->get()->keyBy('codigo_cups');
        $creadas = [];

        foreach ($definiciones as $d) {
            $autor = $d['autor'] === 'admin' ? $this->admin : ($this->profesionales[$d['autor']] ?? $this->admin);
            $procedimiento = $d['cups'] !== null ? $procedimientos->get($d['cups']) : null;
            $fecha = CarbonImmutable::parse($d['mes'].'-'.str_pad((string) mt_rand(3, 26), 2, '0', STR_PAD_LEFT).' 10:00');
            $estado = EstadoLeccion::from($d['estado']);

            $leccion = new LeccionAprendida([
                'tipo' => $d['tipo'],
                'titulo' => $d['titulo'],
                'contexto' => $d['contexto'],
                'leccion' => $d['leccion'],
                'recomendacion' => $d['recomendacion'],
                'categoria' => $d['categoria'],
                'causa' => $d['causa'],
                'procedimiento_quirurgico_id' => $procedimiento?->id,
                'especialidad' => $procedimiento?->especialidad,
                'impacto_estimado_cop' => $d['impacto'],
                'etiquetas' => [...$d['etiquetas'], 'semilla-carga'],
            ]);

            $publicada = in_array($estado, [EstadoLeccion::Publicada, EstadoLeccion::Archivada], true);
            $leccion->forceFill([
                'hospital_id' => $this->hospital->id,
                'estado' => $estado,
                'autor_id' => $autor->id,
                'revisado_por' => $publicada || isset($d['devuelta']) ? $this->admin->id : null,
                'revisado_en' => $publicada || isset($d['devuelta']) ? $fecha->addDays(2) : null,
                'publicada_en' => $publicada ? $fecha->addDays(2) : null,
                'motivo_devolucion' => $d['devuelta'] ?? null,
                'compartida_red' => (bool) $d['red'],
                'compartida_en' => $d['red'] ? $fecha->addDays(5) : null,
                'created_at' => $fecha,
                'updated_at' => $fecha->addDays(2),
            ])->save();

            // Evidencia: hasta tres cirugías del procedimiento con sobrecosto alto.
            if ($procedimiento !== null) {
                $leccion->cirugias()->sync(
                    DB::table('costos_cirugia')
                        ->join('cirugia_procedimiento', 'cirugia_procedimiento.cirugia_id', '=', 'costos_cirugia.cirugia_id')
                        ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimiento->id)
                        ->where('costos_cirugia.clasificacion_desviacion', 'sobrecosto_alto')
                        ->orderByDesc('costos_cirugia.desviacion')
                        ->limit(3)
                        ->pluck('costos_cirugia.cirugia_id')
                        ->all(),
                );
            }

            // Lectores que la marcaron útil.
            if ($estado === EstadoLeccion::Publicada) {
                $lectores = collect($this->profesionales)->shuffle()->take(mt_rand(1, 8))->pluck('id')->all();
                $leccion->valoraciones()->syncWithoutDetaching($lectores);
            }

            $creadas[] = $leccion;
        }

        return $creadas;
    }

    /**
     * Lecciones del otro hospital publicadas en la red: lo que San Rafael ve
     * en la pestaña «Red de hospitales».
     *
     * @param  list<array<string, mixed>>  $definiciones
     */
    protected function crearLeccionesDeLaRed(array $definiciones): void
    {
        $riohacha = Hospital::query()->where('nit', '800300400-2')->first();
        $autor = User::query()->where('email', 'admin@riohacha.test')->first();

        if ($riohacha === null) {
            return;
        }

        foreach ($definiciones as $d) {
            $fecha = CarbonImmutable::parse($d['mes'].'-15 09:00');
            $procedimientoId = $d['cups'] === null ? null : ProcedimientoQuirurgico::query()->withoutGlobalScopes()
                ->where('hospital_id', $riohacha->id)->where('codigo_cups', $d['cups'])->value('id');

            $leccion = new LeccionAprendida([
                'tipo' => $d['tipo'],
                'titulo' => $d['titulo'],
                'contexto' => $d['contexto'],
                'leccion' => $d['leccion'],
                'recomendacion' => $d['recomendacion'],
                'categoria' => $d['categoria'],
                'procedimiento_quirurgico_id' => $procedimientoId,
                'especialidad' => $d['especialidad'],
                'etiquetas' => ['semilla-carga'],
            ]);
            $leccion->forceFill([
                'hospital_id' => $riohacha->id,
                'estado' => EstadoLeccion::Publicada,
                'autor_id' => $autor?->id,
                'revisado_por' => $autor?->id,
                'revisado_en' => $fecha,
                'publicada_en' => $fecha,
                'compartida_red' => true,
                'compartida_en' => $fecha,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ])->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @param  list<LeccionAprendida>  $lecciones
     */
    protected function crearHilos(array $definiciones, array $lecciones): void
    {
        $comunidades = ComunidadPractica::query()->get()->keyBy('nombre');
        $procedimientos = ProcedimientoQuirurgico::query()->get()->keyBy('codigo_cups');

        foreach ($definiciones as $i => $d) {
            $nombre = str_starts_with($d['comunidad'], 'Comunidad') ? $d['comunidad'] : "Comunidad de {$d['comunidad']}";
            $comunidad = $comunidades->get($nombre);

            if ($comunidad === null) {
                continue;
            }

            $inicio = now()->subDays(80 - $i * 12)->setTime(9 + $i, 15);
            [$enlaceTipo, $enlaceId] = match (true) {
                $d['enlace'] === null => [null, null],
                $d['enlace'][0] === 'procedimiento' => ['procedimiento', $procedimientos->get($d['enlace'][1])?->id],
                default => [null, null],
            };

            $hilo = new HiloComunidad([
                'comunidad_practica_id' => $comunidad->id,
                'hospital_id' => $this->hospital->id,
                'autor_id' => $this->usuario($d['autor'])->id,
                'titulo' => $d['titulo'],
                'cuerpo' => $d['cuerpo'],
                'enlazable_tipo' => $enlaceTipo,
                'enlazable_id' => $enlaceId,
                'leccion_aprendida_id' => $d['leccion'] !== null ? ($lecciones[$d['leccion']]->id ?? null) : null,
                'ultima_actividad_en' => $inicio,
            ]);
            $hilo->forceFill(['created_at' => $inicio, 'updated_at' => $inicio])->save();

            $momento = $inicio;
            foreach ($d['respuestas'] as $r) {
                $momento = $momento->addHours(mt_rand(3, 30));
                $respuesta = $hilo->respuestas()->make([
                    'autor_id' => $this->usuario($r[0])->id,
                    'cuerpo' => $r[1],
                    'es_solucion' => (bool) ($r[2] ?? false),
                ]);
                $respuesta->forceFill(['created_at' => $momento, 'updated_at' => $momento])->save();
            }

            $hilo->forceFill(['ultima_actividad_en' => $momento])->save();
        }
    }

    protected function usuario(string $clave): User
    {
        return $clave === 'admin' ? $this->admin : ($this->profesionales[$clave] ?? $this->admin);
    }

    /**
     * Como lo haría un comité: acepta las tres de mayor ahorro con
     * responsable, da por implementadas dos y descarta dos con motivo.
     */
    protected function gestionarRecomendaciones(): void
    {
        $nuevas = Recomendacion::query()->where('estado', EstadoRecomendacion::Nueva->value)
            ->orderByDesc('ahorro_mensual_estimado')
            ->get();
        $responsables = [$this->admin, ...array_values($this->profesionales)];

        foreach ($nuevas->take(5)->values() as $i => $recomendacion) {
            $estado = $i < 2 ? EstadoRecomendacion::Implementada : EstadoRecomendacion::Aceptada;
            $recomendacion->forceFill([
                'estado' => $estado,
                'responsable_id' => $responsables[$i % count($responsables)]->id,
                'fecha_compromiso' => now()->addDays(30 + $i * 15)->toDateString(),
                'nota' => $i < 2 ? 'Aprobado en el comité de costos; ya en marcha.' : null,
                'gestionada_por' => $this->admin->id,
                'gestionada_en' => now()->subDays(20 - $i),
                'implementada_en' => $estado === EstadoRecomendacion::Implementada ? now()->subDays(5) : null,
            ])->save();
        }

        foreach ($nuevas->slice(-2) as $recomendacion) {
            $recomendacion->forceFill([
                'estado' => EstadoRecomendacion::Descartada,
                'nota' => 'El volumen del procedimiento es bajo; no compensa el esfuerzo por ahora.',
                'gestionada_por' => $this->admin->id,
                'gestionada_en' => now()->subDays(10),
            ])->save();
        }
    }

    protected function resumir(): void
    {
        $this->tabla(['Conocimiento de San Rafael', 'Total'], [
            ['Profesionales con cuenta', (string) count($this->profesionales)],
            ['Alertas de sobrecosto revisadas', (string) AlertaSobrecosto::query()->where('estado', '!=', EstadoAlerta::Pendiente->value)->count()],
            ['Alertas de reglas', (string) AlertaConocimiento::query()->count()],
            ['  revisadas o descartadas', (string) AlertaConocimiento::query()->where('estado', '!=', EstadoAlerta::Pendiente->value)->count()],
            ['Lecciones', (string) LeccionAprendida::query()->count()],
            ['  publicadas', (string) LeccionAprendida::query()->where('estado', EstadoLeccion::Publicada->value)->count()],
            ['Comunidades', (string) ComunidadPractica::query()->count()],
            ['Hilos', (string) HiloComunidad::query()->count()],
            ['Recomendaciones', (string) Recomendacion::query()->count()],
        ]);

        if ($this->claves !== []) {
            $this->advertir(filled(config('seeding.user_password'))
                ? 'Profesionales creados con la contraseña de config/seeding.php:'
                : 'Contraseñas generadas — cópialas AHORA, no se vuelven a mostrar:');
            $this->tabla(['Correo', 'Contraseña'], $this->claves);
        }
    }
}
