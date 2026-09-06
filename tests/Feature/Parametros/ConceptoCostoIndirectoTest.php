<?php

namespace Tests\Feature\Parametros;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Scopes\HospitalScope;

/**
 * Catálogo de bolsas CIF: alta, validaciones excluyentes de monto/porcentaje,
 * vigencias sin solape y aislamiento por hospital.
 */
class ConceptoCostoIndirectoTest extends ParametrosTestCase
{
    public function test_registra_un_concepto_y_nace_inactivo(): void
    {
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos())
            ->assertSessionHasNoErrors();

        $concepto = ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)->sole();

        $this->assertSame('Energía eléctrica', $concepto->nombre);
        $this->assertSame($this->hospitalA->id, $concepto->hospital_id);
        $this->assertEqualsWithDelta(20_000_000, (float) $concepto->monto_mensual, 0.01);

        // Encender la bolsa excluye el componente directo equivalente: eso lo
        // decide ActivarCategoriaCif, nunca el alta del catálogo.
        $this->assertFalse($concepto->activo);
    }

    public function test_el_porcentaje_es_obligatorio_solo_en_la_base_porcentaje_directo(): void
    {
        // Falta el porcentaje que esa base exige.
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'base_asignacion' => BaseAsignacionCif::PorcentajeDirecto->value,
                'monto_mensual' => null,
            ]))
            ->assertSessionHasErrors('porcentaje');

        // Con porcentaje y sin monto, pasa.
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'base_asignacion' => BaseAsignacionCif::PorcentajeDirecto->value,
                'monto_mensual' => null,
                'porcentaje' => 0.05,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_monto_y_porcentaje_se_excluyen_mutuamente(): void
    {
        // Porcentaje en una base por minuto: no se usaría nunca.
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos(['porcentaje' => 0.05]))
            ->assertSessionHasErrors('porcentaje');

        // Monto en una base por porcentaje: el motor tendría dos fuentes.
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'base_asignacion' => BaseAsignacionCif::PorcentajeDirecto->value,
                'porcentaje' => 0.05,
            ]))
            ->assertSessionHasErrors('monto_mensual');
    }

    public function test_el_monto_mensual_es_obligatorio_en_las_bases_por_minuto(): void
    {
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos(['monto_mensual' => null]))
            ->assertSessionHasErrors('monto_mensual');
    }

    public function test_rechaza_vigencias_solapadas_del_mismo_concepto(): void
    {
        ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Energía eléctrica',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-06-30',
        ]);

        // Empieza dentro de la vigencia existente.
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'vigente_desde' => '2026-06-01',
                'vigente_hasta' => '2026-12-31',
            ]))
            ->assertSessionHasErrors('vigente_desde');
    }

    public function test_acepta_vigencias_consecutivas_sin_solape(): void
    {
        ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Energía eléctrica',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-06-30',
        ]);

        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'vigente_desde' => '2026-07-01',
                'vigente_hasta' => null,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_una_vigencia_abierta_bloquea_cualquier_posterior(): void
    {
        ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Energía eléctrica',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos([
                'vigente_desde' => '2027-01-01',
            ]))
            ->assertSessionHasErrors('vigente_desde');
    }

    public function test_editar_un_concepto_no_choca_consigo_mismo(): void
    {
        $concepto = ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Energía eléctrica',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        $this->actingAs($this->adminA)
            ->put("/parametros/costos-indirectos/{$concepto->id}", $this->datos([
                'monto_mensual' => 24_000_000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(
            24_000_000,
            (float) $concepto->fresh()->monto_mensual,
            0.01,
        );
    }

    public function test_el_solape_se_evalua_por_hospital(): void
    {
        // Mismo nombre y misma vigencia, pero en el otro hospital: no choca.
        ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalB->id,
            'nombre' => 'Energía eléctrica',
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos())
            ->assertSessionHasNoErrors();
    }

    public function test_el_listado_solo_muestra_conceptos_del_hospital_propio(): void
    {
        ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalB->id,
            'nombre' => 'Concepto del hospital B',
        ]);

        $this->actingAs($this->adminA)
            ->get('/parametros/costos-indirectos')
            ->assertOk()
            ->assertDontSee('Concepto del hospital B');
    }

    public function test_no_se_puede_eliminar_un_concepto_activo(): void
    {
        $concepto = ConceptoCostoIndirecto::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'activo' => true,
        ]);

        $this->actingAs($this->adminA)
            ->delete("/parametros/costos-indirectos/{$concepto->id}");

        $this->assertDatabaseHas('conceptos_costo_indirecto', ['id' => $concepto->id]);
    }

    /**
     * `activo` fuera del `$fillable`: el invariante «bolsa y componente
     * directo nunca ambos» solo se sostiene si nadie puede encenderla por
     * asignación masiva desde el CRUD.
     */
    public function test_el_crud_no_puede_activar_un_concepto_por_asignacion_masiva(): void
    {
        $this->actingAs($this->adminA)
            ->post('/parametros/costos-indirectos', $this->datos(['activo' => true]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)->sole()->activo,
        );
    }

    /** @param array<string, mixed> $sobrescribir */
    private function datos(array $sobrescribir = []): array
    {
        return array_merge([
            'nombre' => 'Energía eléctrica',
            'categoria' => CategoriaCif::Infraestructura->value,
            'base_asignacion' => BaseAsignacionCif::MinutoQuirofano->value,
            'monto_mensual' => 20_000_000,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'nivel_confiabilidad' => 'estimado',
        ], $sobrescribir);
    }
}
