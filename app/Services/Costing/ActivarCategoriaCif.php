<?php

namespace App\Services\Costing;

use App\Enums\CategoriaCif;
use App\Enums\OrigenComponente;
use App\Exceptions\SolapeDeCostoIndirectoException;
use App\Models\ConceptoCostoIndirecto;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\Scopes\HospitalScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Enciende las bolsas CIF de una categoría y, en la misma transacción, marca
 * como derivado de CIF el componente directo que esa categoría reemplaza.
 *
 * Es el ÚNICO punto que escribe `conceptos_costo_indirecto.activo` y las
 * columnas `origen_*` de `hospitales`. La razón es el invariante: si el flag
 * y el origen se pudieran mover por separado, un hospital quedaría con el
 * costo directo y la bolsa activos a la vez —doble conteo— o con ninguno de
 * los dos —costo perdido—. Por eso `activo` está fuera del `$fillable` del
 * modelo y el CRUD crea siempre conceptos apagados.
 *
 * Activar exige confirmación explícita cuando el componente equivalente
 * todavía tiene valores digitados: no es un error recuperable con un
 * reintento, es una decisión del usuario sobre sus propios datos.
 */
class ActivarCategoriaCif
{
    /**
     * @param  bool  $confirmado  el usuario aceptó que el componente directo
     *                            pase a derivarse de la bolsa
     * @return int conceptos activados
     *
     * @throws SolapeDeCostoIndirectoException si falta esa confirmación
     */
    public function ejecutar(Hospital $hospital, CategoriaCif $categoria, bool $confirmado = false): int
    {
        $ids = $this->idsDeConceptos($hospital, $categoria);

        if ($ids === []) {
            throw new RuntimeException(
                "El hospital {$hospital->id} no tiene conceptos de «{$categoria->value}» que activar.",
            );
        }

        $columnaOrigen = $categoria->columnaOrigen();

        // Categorías sin equivalente en el directo (administración, servicios
        // generales) no pueden duplicar nada: se activan sin más.
        if ($columnaOrigen !== null && ! $confirmado) {
            $conflictos = $this->componentesDigitados($hospital, $categoria);

            if ($conflictos !== []) {
                throw SolapeDeCostoIndirectoException::porComponenteDigitado($categoria, $conflictos);
            }
        }

        return DB::transaction(function () use ($hospital, $columnaOrigen, $ids): int {
            $activados = ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)
                ->whereKey($ids)
                ->update(['activo' => true]);

            if ($columnaOrigen !== null) {
                $hospital->forceFill([
                    $columnaOrigen => OrigenComponente::DerivadoDeCif->value,
                ])->save();
            }

            return $activados;
        });
    }

    /**
     * Registros activos cuyo campo equivalente sigue digitado con valor > 0.
     * Un cero es legítimo (sala prestada, equipo donado) y no estorba.
     *
     * @return list<array{nombre: string, valor: float}>
     */
    public function componentesDigitados(Hospital $hospital, CategoriaCif $categoria): array
    {
        [$modelo, $columna, $banderaActivo] = match ($categoria) {
            CategoriaCif::Infraestructura => [SalaOperatoria::class, 'costo_hora', 'activa'],
            CategoriaCif::DepreciacionEquipos => [EquipoMedico::class, 'costo_hora', 'activo'],
            CategoriaCif::PersonalIndirecto => [RecursoHumano::class, 'costos_indirectos_mensuales', 'activo'],
            default => [null, null, null],
        };

        if ($modelo === null) {
            return [];
        }

        $filas = $modelo::withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where($banderaActivo, true)
            ->where($columna, '>', 0)
            ->orderBy('nombre')
            ->get(['nombre', $columna]);

        $conflictos = [];

        foreach ($filas as $fila) {
            $conflictos[] = [
                'nombre' => (string) $fila->getAttribute('nombre'),
                'valor' => (float) $fila->getAttribute($columna),
            ];
        }

        return $conflictos;
    }

    /**
     * Ids de los conceptos de la categoría en ese hospital.
     *
     * @return list<int>
     */
    private function idsDeConceptos(Hospital $hospital, CategoriaCif $categoria): array
    {
        $ids = [];

        $conceptos = ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where('categoria', $categoria->value)
            ->get(['id']);

        foreach ($conceptos as $concepto) {
            $ids[] = (int) $concepto->getKey();
        }

        return $ids;
    }
}
