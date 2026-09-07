<?php

namespace Tests\Feature\Costeo;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Exceptions\CapacidadCifNoDisponibleException;
use App\Models\Cirugia;
use App\Models\CirugiaConceptoIndirecto;
use App\Models\ConceptoCostoIndirecto;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\ActivarCategoriaCif;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de asignación de bolsas CIF.
 *
 * Lo que se prueba aquí no es que «el número salga»: es que el indirecto deje
 * de ser un porcentaje ciego y pase a repartirse por el inductor de cada
 * bolsa, que el componente directo equivalente desaparezca del directo cuando
 * la bolsa lo cubre, y que las tres sumas que un auditor comprobaría —líneas
 * del pivote, desglose por fase y total— cuadren al centavo.
 *
 * Escenario base: 12 h/día × 26 días × 60 min = 18.720 min por recurso.
 * Con 3 salas activas, la capacidad de quirófano del hospital es 56.160
 * min/mes. Una cirugía de 120 minutos consume 120/56.160 de la bolsa.
 */
class MotorCifTest extends TestCase
{
    use RefreshDatabase;

    private const DURACION = 120;

    /** 28.080.000 ÷ 56.160 min = $500/min exactos. */
    private const MONTO_QUIROFANO = 28_080_000;

    /** Salario que a 18.720 min da $100/min exactos. */
    private const SALARIO = 1_872_000;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    // ── La vía tradicional sigue intacta ────────────────────────────────

    public function test_sin_bolsas_el_indirecto_es_el_factor_plano(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.10);
        $costo = $this->costear($hospital);

        $this->assertSame('factor', $costo->detalle['indirecto']['via']);
        $this->assertEqualsWithDelta(
            round((float) $costo->costo_directo * 0.10, 2),
            (float) $costo->costo_indirecto,
            0.01,
        );
        $this->assertSame([], $costo->detalle['indirecto']['bolsas']);
    }

    // ── Reparto por inductor ────────────────────────────────────────────

    public function test_una_bolsa_por_minuto_de_quirofano_se_reparte_por_la_capacidad_sumada(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.10);
        $this->bolsa($hospital, self::MONTO_QUIROFANO);
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $costo = $this->costear($hospital);

        // 28.080.000 ÷ (3 salas × 18.720) = $500/min × 120 min = 60.000
        $this->assertEqualsWithDelta(60_000.0, (float) $costo->costo_indirecto, 0.01);

        $linea = CirugiaConceptoIndirecto::withoutGlobalScopes()->sole();
        $this->assertSame(56_160, $linea->denominador_registrado);
        $this->assertEqualsWithDelta(500.0, (float) $linea->tasa_registrada, 0.000001);
        $this->assertEqualsWithDelta(120.0, (float) $linea->unidades_aplicadas, 0.01);
        $this->assertEqualsWithDelta(60_000.0, (float) $linea->monto_asignado, 0.01);
    }

    /**
     * El error que este mecanismo evita: usar la capacidad de UN quirófano
     * como denominador de una bolsa que cubre todos multiplicaría la tasa por
     * el número de salas.
     */
    public function test_la_tasa_baja_cuando_el_hospital_tiene_mas_salas(): void
    {
        $unaSala = $this->hospital(salas: 1);
        $this->bolsa($unaSala, self::MONTO_QUIROFANO);
        $this->activar($unaSala, CategoriaCif::Infraestructura);

        $tresSalas = $this->hospital(salas: 3);
        $this->bolsa($tresSalas, self::MONTO_QUIROFANO);
        $this->activar($tresSalas, CategoriaCif::Infraestructura);

        $conUna = (float) $this->costear($unaSala)->costo_indirecto;
        $conTres = (float) $this->costear($tresSalas)->costo_indirecto;

        $this->assertEqualsWithDelta(180_000.0, $conUna, 0.01);
        $this->assertEqualsWithDelta(60_000.0, $conTres, 0.01);
        $this->assertEqualsWithDelta(3.0, $conUna / $conTres, 0.0001);
    }

    public function test_una_bolsa_por_minuto_de_personal_usa_la_capacidad_del_equipo_quirurgico(): void
    {
        $hospital = $this->hospital();
        // Un solo recurso quirúrgico activo ⇒ denominador 18.720.
        $this->bolsa($hospital, 18_720_000, BaseAsignacionCif::MinutoPersonal, CategoriaCif::Administracion);
        $this->activar($hospital, CategoriaCif::Administracion);

        $costo = $this->costear($hospital);

        // 18.720.000 ÷ 18.720 = $1.000/min × 120 min de participación
        $this->assertEqualsWithDelta(120_000.0, (float) $costo->costo_indirecto, 0.01);
        $this->assertSame(18_720, CirugiaConceptoIndirecto::withoutGlobalScopes()->sole()->denominador_registrado);
    }

    public function test_una_bolsa_por_porcentaje_se_calcula_sobre_el_costo_directo(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.30);
        ConceptoCostoIndirecto::factory()
            ->porcentajeDirecto(0.08)
            ->categoria(CategoriaCif::ServiciosGenerales)
            ->create(['hospital_id' => $hospital->id, 'nombre' => 'Lavandería']);
        $this->activar($hospital, CategoriaCif::ServiciosGenerales);

        $costo = $this->costear($hospital);

        $this->assertEqualsWithDelta(
            round((float) $costo->costo_directo * 0.08, 2),
            (float) $costo->costo_indirecto,
            0.01,
        );
    }

    // ── Anti-doble-conteo, ahora con efecto real ────────────────────────

    public function test_activar_infraestructura_saca_la_sala_del_costo_directo(): void
    {
        $hospital = $this->hospital();
        $sinBolsa = $this->costear($hospital);

        $conBolsa = $this->hospital();
        $this->bolsa($conBolsa, self::MONTO_QUIROFANO);
        $this->activar($conBolsa, CategoriaCif::Infraestructura);
        $costo = $this->costear($conBolsa);

        // La sala costaba 60.000/h × 2 h = 120.000 en el directo.
        $this->assertEqualsWithDelta(120_000.0, (float) $sinBolsa->costo_sala, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $costo->costo_sala, 0.01);
        $this->assertTrue($costo->detalle['sala']['derivado_de_cif']);
        // La tarifa digitada sigue visible: es lo que explica el cero.
        $this->assertEqualsWithDelta(60_000.0, (float) $costo->detalle['sala']['costo_hora'], 0.01);
        // Y el costo no desaparece: entra por la bolsa.
        $this->assertEqualsWithDelta(60_000.0, (float) $costo->costo_indirecto, 0.01);
    }

    public function test_la_bolsa_de_personal_indirecto_excluye_esos_indirectos_del_directo(): void
    {
        $sinBolsa = $this->hospital(indirectosPersonal: 936_000);
        $conBolsa = $this->hospital(indirectosPersonal: 936_000);

        $this->bolsa($conBolsa, 18_720_000, BaseAsignacionCif::MinutoPersonal, CategoriaCif::PersonalIndirecto);
        $this->activar($conBolsa, CategoriaCif::PersonalIndirecto, confirmado: true);

        // salario 1.872.000 + indirectos 936.000 ⇒ $150/min digitado, $100/min derivado
        $this->assertEqualsWithDelta(
            18_000.0,
            (float) $this->costear($sinBolsa)->costo_recurso_humano,
            0.01,
        );
        $this->assertEqualsWithDelta(
            12_000.0,
            (float) $this->costear($conBolsa)->costo_recurso_humano,
            0.01,
        );
    }

    public function test_el_factor_indirecto_se_ignora_cuando_hay_bolsas(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.50);
        $this->bolsa($hospital, self::MONTO_QUIROFANO);
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $costo = $this->costear($hospital);

        $this->assertSame('bolsas', $costo->detalle['indirecto']['via']);
        $this->assertNull($costo->detalle['indirecto']['factor_indirecto']);
        // Con el factor sumado además de la bolsa, esto daría mucho más.
        $this->assertEqualsWithDelta(60_000.0, (float) $costo->costo_indirecto, 0.01);
    }

    // ── Cuadre al centavo ───────────────────────────────────────────────

    public function test_las_lineas_del_pivote_suman_exactamente_el_costo_indirecto(): void
    {
        $hospital = $this->hospital();

        // Tres bolsas con residuo: 1.000.000 ÷ 468 no es un número redondo.
        foreach (['Energía', 'Aseo', 'Vigilancia'] as $nombre) {
            $this->bolsa($hospital, 1_000_000, nombre: $nombre);
        }
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $costo = $this->costear($hospital);
        $lineas = CirugiaConceptoIndirecto::withoutGlobalScopes()->get();

        $this->assertCount(3, $lineas);
        $this->assertSame(
            (int) round((float) $costo->costo_indirecto * 100),
            (int) round($lineas->sum(fn (CirugiaConceptoIndirecto $l): float => (float) $l->monto_asignado) * 100),
        );
    }

    public function test_el_indirecto_por_fase_suma_el_costo_indirecto(): void
    {
        $hospital = $this->hospital();
        $this->bolsa($hospital, 1_000_000);
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $costo = $this->costear($hospital);

        $this->assertSame(
            (int) round((float) $costo->costo_indirecto * 100),
            (int) round(array_sum($costo->detalle['indirecto_por_fase']) * 100),
        );
    }

    public function test_recostear_reemplaza_las_lineas_en_vez_de_duplicarlas(): void
    {
        $hospital = $this->hospital();
        $this->bolsa($hospital, self::MONTO_QUIROFANO);
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $cirugia = $this->registrar($hospital);
        app(TdabcCostingService::class)->calcular($cirugia);
        app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertSame(1, CirugiaConceptoIndirecto::withoutGlobalScopes()->count());
    }

    // ── El snapshot protege la historia ─────────────────────────────────

    public function test_una_cirugia_registrada_antes_de_activar_conserva_su_via(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.10);
        $cirugia = $this->registrar($hospital);
        $antes = app(TdabcCostingService::class)->calcular($cirugia);

        $this->bolsa($hospital, self::MONTO_QUIROFANO);
        $this->activar($hospital->fresh(), CategoriaCif::Infraestructura, confirmado: true);

        $despues = app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertSame('factor', $despues->detalle['indirecto']['via']);
        $this->assertEqualsWithDelta(
            (float) $antes->costo_indirecto,
            (float) $despues->costo_indirecto,
            0.01,
        );
        $this->assertEqualsWithDelta((float) $antes->costo_sala, (float) $despues->costo_sala, 0.01);
    }

    public function test_una_bolsa_fuera_de_vigencia_no_entra(): void
    {
        $hospital = $this->hospital(factorIndirecto: 0.10);
        $this->bolsa($hospital, self::MONTO_QUIROFANO);
        ConceptoCostoIndirecto::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            // La cirugía es del 2026-06-10.
            ->update(['vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-03-31']);
        $this->activar($hospital, CategoriaCif::Infraestructura);

        $costo = $this->costear($hospital);

        $this->assertSame('factor', $costo->detalle['indirecto']['via']);
        // Y sin bolsa vigente, la sala vuelve al costo directo: el mecanismo
        // nunca deja un componente fuera de las dos vías.
        $this->assertEqualsWithDelta(120_000.0, (float) $costo->costo_sala, 0.01);
    }

    // ── Guardas ─────────────────────────────────────────────────────────

    public function test_no_se_puede_activar_una_bolsa_por_minuto_sin_capacidad(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);
        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        $this->expectException(CapacidadCifNoDisponibleException::class);

        app(ActivarCategoriaCif::class)
            ->ejecutar($hospital, CategoriaCif::Infraestructura, confirmado: true);
    }

    // ── Utilidades ──────────────────────────────────────────────────────

    /**
     * Hospital con sus salas y su cirujano ya dados de alta.
     *
     * El personal se crea aquí y no al registrar la cirugía porque las bolsas
     * por minuto de personal necesitan capacidad ANTES de encenderse: activar
     * una bolsa en un hospital sin plantilla es justamente lo que la guarda
     * de capacidad impide.
     */
    private function hospital(
        int $salas = 3,
        float $factorIndirecto = 0.0,
        float $indirectosPersonal = 0,
    ): Hospital {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => 60,
            'factor_indirecto' => $factorIndirecto,
        ]);

        foreach (range(1, $salas) as $i) {
            SalaOperatoria::factory()->create([
                'hospital_id' => $hospital->id,
                'nombre' => "Quirófano {$i}",
                // Solo la primera tiene tarifa: es la que usa la cirugía.
                'costo_hora' => $i === 1 ? 60_000 : 0,
            ]);
        }

        RecursoHumano::factory()->create([
            'hospital_id' => $hospital->id,
            'rol' => RolQuirurgico::Cirujano->value,
            'salario_mensual' => self::SALARIO,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => $indirectosPersonal,
        ]);

        return $hospital;
    }

    private function bolsa(
        Hospital $hospital,
        float $monto,
        BaseAsignacionCif $base = BaseAsignacionCif::MinutoQuirofano,
        CategoriaCif $categoria = CategoriaCif::Infraestructura,
        string $nombre = 'Energía eléctrica',
    ): ConceptoCostoIndirecto {
        return ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => $nombre,
            'categoria' => $categoria->value,
            'base_asignacion' => $base->value,
            'monto_mensual' => $monto,
            'vigente_desde' => '2026-01-01',
        ]);
    }

    private function activar(Hospital $hospital, CategoriaCif $categoria, bool $confirmado = true): void
    {
        HospitalContext::set($hospital->id);
        app(ActivarCategoriaCif::class)->ejecutar($hospital, $categoria, $confirmado);
    }

    private function costear(Hospital $hospital): CostoCirugia
    {
        return app(TdabcCostingService::class)->calcular($this->registrar($hospital));
    }

    /** Cirugía de 120 min con el cirujano y la primera sala del hospital. */
    private function registrar(Hospital $hospital): Cirugia
    {
        HospitalContext::set($hospital->id);

        $cirujano = RecursoHumano::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            ->orderBy('id')
            ->firstOrFail();

        $sala = SalaOperatoria::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            ->orderBy('id')
            ->firstOrFail();

        return app(RegistrarCirugia::class)->ejecutar([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
            'tipo' => 'programada',
            'estado' => EstadoCirugia::Realizada->value,
            'procedimientos' => [[
                'id' => ProcedimientoQuirurgico::factory()->create(['hospital_id' => $hospital->id])->id,
                'es_principal' => true,
            ]],
            'equipo' => [[
                'recurso_humano_id' => $cirujano->id,
                'rol' => RolQuirurgico::Cirujano->value,
                'fase' => FaseCiclo::Quirurgica->value,
                'minutos_participacion' => self::DURACION,
            ]],
        ]);
    }
}
