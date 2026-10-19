<?php

namespace App\Services\Costing;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\OrigenComponente;
use App\Exceptions\CapacidadCifNoDisponibleException;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Models\Scopes\HospitalScope;
use Carbon\CarbonInterface;

/**
 * Reparte las bolsas de costo indirecto entre las cirugías.
 *
 * Hace dos cosas separadas a propósito:
 *
 * 1. `congelar()` fotografía CÓMO se va a costear una cirugía —vía aplicada,
 *    origen de cada componente, denominador de cada base y tasa de cada
 *    bolsa— en el momento del registro. Sin esa foto, recostear una cirugía
 *    del año pasado usaría las salas y las bolsas de hoy.
 * 2. `asignar()` toma esa foto y las unidades de la cirugía concreta
 *    (minutos de quirófano, minutos de personal, costo directo) y devuelve
 *    cuánto pone cada bolsa.
 *
 * El redondeo ocurre UNA sola vez: cada bolsa se calcula a precisión
 * completa, el total se redondea a centavos y las líneas se cuadran contra
 * ese total por mayor resto. Redondear línea a línea dejaba diferencias de
 * centavos entre el desglose y el total, que en una auditoría contable son
 * un error, no un detalle.
 */
class AsignadorCif
{
    /**
     * Foto de los parámetros CIF vigentes para una cirugía de esa fecha.
     *
     * `via` distingue las dos formas de calcular el indirecto: `bolsas`
     * cuando hay al menos un concepto activo y vigente, `factor` cuando no.
     * El `factor_indirecto` del hospital se ignora en la primera (decisión 6
     * del diseño): son dos métodos alternativos, no acumulativos.
     *
     * Un componente solo se marca `derivado_de_cif` si además existe una
     * bolsa vigente de esa categoría. Sin esa condición, una bolsa que vence
     * y no se renueva dejaría el componente fuera del directo y sin nada que
     * lo sustituya: costo perdido en silencio.
     *
     * @return array{
     *     via: string,
     *     origenes: array<string, string>,
     *     denominadores: array<string, int>,
     *     conceptos: list<array{
     *         id: int, nombre: string, categoria: string, base_asignacion: string,
     *         monto_mensual: float|null, porcentaje: float|null,
     *         denominador: int|null, tasa: float
     *     }>
     * }
     */
    public function congelar(Hospital $hospital, CarbonInterface $fecha): array
    {
        $conceptos = ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->aplicablesEn($fecha)
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get();

        $denominadores = $hospital->denominadoresCif();
        $congelados = [];
        $categoriasConBolsa = [];

        foreach ($conceptos as $concepto) {
            $base = $concepto->base_asignacion;
            $denominador = $base->usaPorcentaje() ? null : ($denominadores[$base->value] ?? 0);

            if ($denominador !== null && $denominador <= 0) {
                throw CapacidadCifNoDisponibleException::porBase($hospital, $base, $concepto->nombre);
            }

            $congelados[] = [
                'id' => (int) $concepto->getKey(),
                'nombre' => $concepto->nombre,
                'categoria' => $concepto->categoria->value,
                'base_asignacion' => $base->value,
                'monto_mensual' => $concepto->monto_mensual !== null
                    ? (float) $concepto->monto_mensual
                    : null,
                'porcentaje' => $concepto->porcentaje !== null
                    ? (float) $concepto->porcentaje
                    : null,
                'denominador' => $denominador,
                'tasa' => $base->usaPorcentaje()
                    ? (float) $concepto->porcentaje
                    : (float) $concepto->monto_mensual / $denominador,
            ];

            $categoriasConBolsa[$concepto->categoria->value] = true;
        }

        $origenes = [];

        foreach ($hospital->origenesDeComponentes() as $categoria => $origen) {
            $origenes[$categoria] = $origen === OrigenComponente::DerivadoDeCif->value
                && isset($categoriasConBolsa[$categoria])
                ? OrigenComponente::DerivadoDeCif->value
                : OrigenComponente::Digitado->value;
        }

        return [
            'via' => $congelados === [] ? 'factor' : 'bolsas',
            'origenes' => $origenes,
            'denominadores' => $denominadores,
            'conceptos' => $congelados,
        ];
    }

    /**
     * Reparte las bolsas congeladas sobre una cirugía.
     *
     * @param  array<string, mixed>  $parametros  lo devuelto por congelar()
     * @param  array{minuto_quirofano: float, minuto_personal: float, costo_directo: float}  $unidades
     * @return array{total: float, lineas: list<array<string, mixed>>}
     */
    public function asignar(array $parametros, array $unidades): array
    {
        /** @var list<array<string, mixed>> $conceptos */
        $conceptos = $parametros['conceptos'] ?? [];

        if ($conceptos === []) {
            return ['total' => 0.0, 'lineas' => []];
        }

        $brutos = [];
        $lineas = [];

        foreach ($conceptos as $indice => $concepto) {
            $base = BaseAsignacionCif::from((string) $concepto['base_asignacion']);
            $tasa = (float) $concepto['tasa'];
            $aplicadas = $this->unidadesDe($base, $unidades);

            $brutos[$indice] = $tasa * $aplicadas;

            $lineas[$indice] = [
                'concepto_costo_indirecto_id' => (int) $concepto['id'],
                'nombre' => (string) $concepto['nombre'],
                'categoria' => (string) $concepto['categoria'],
                'base_asignacion' => $base->value,
                'monto_mensual' => $concepto['monto_mensual'] ?? null,
                'porcentaje' => $concepto['porcentaje'] ?? null,
                'denominador' => $concepto['denominador'] ?? null,
                'tasa' => $tasa,
                'unidades' => $aplicadas,
            ];
        }

        $total = round(array_sum($brutos), 2);

        foreach (self::repartirPorMayorResto($brutos, (int) round($total * 100)) as $indice => $centavos) {
            $lineas[$indice]['monto'] = $centavos / 100;
        }

        return ['total' => $total, 'lineas' => array_values($lineas)];
    }

    /**
     * Reparte `$totalCentavos` entre `$pesos` de modo que las partes sumen
     * exactamente el total.
     *
     * Se reparte el piso de cada cuota y el sobrante —siempre menor que el
     * número de partes— va de a un centavo a quienes tengan mayor resto
     * decimal. Repartir con `round()` por línea puede sobrar o faltar tanto
     * como partes haya; esto no.
     *
     * @param  array<array-key, float>  $pesos
     * @return array<array-key, int> centavos por clave
     */
    public static function repartirPorMayorResto(array $pesos, int $totalCentavos): array
    {
        $suma = array_sum($pesos);

        if ($pesos === [] || $suma <= 0.0 || $totalCentavos === 0) {
            return array_map(static fn (): int => 0, $pesos);
        }

        $cuotas = [];
        $restos = [];
        $repartido = 0;

        foreach ($pesos as $clave => $peso) {
            $exacto = max(0.0, $peso) / $suma * $totalCentavos;
            $piso = (int) floor($exacto);

            $cuotas[$clave] = $piso;
            $restos[$clave] = $exacto - $piso;
            $repartido += $piso;
        }

        arsort($restos);

        foreach (array_keys(array_slice($restos, 0, $totalCentavos - $repartido, true)) as $clave) {
            $cuotas[$clave]++;
        }

        return $cuotas;
    }

    /**
     * ¿Este componente del costo directo lo cubre ya una bolsa?
     *
     * @param  array<string, mixed>|null  $parametros
     */
    public static function derivadoDeCif(?array $parametros, CategoriaCif $categoria): bool
    {
        /** @var array<string, string> $origenes */
        $origenes = $parametros['origenes'] ?? [];

        return ($origenes[$categoria->value] ?? OrigenComponente::Digitado->value)
            === OrigenComponente::DerivadoDeCif->value;
    }

    /** @param array{minuto_quirofano: float, minuto_personal: float, costo_directo: float} $unidades */
    private function unidadesDe(BaseAsignacionCif $base, array $unidades): float
    {
        return match ($base) {
            BaseAsignacionCif::MinutoQuirofano => (float) $unidades['minuto_quirofano'],
            BaseAsignacionCif::MinutoPersonal => (float) $unidades['minuto_personal'],
            BaseAsignacionCif::PorcentajeDirecto => (float) $unidades['costo_directo'],
        };
    }
}
