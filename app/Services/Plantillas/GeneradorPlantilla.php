<?php

namespace App\Services\Plantillas;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Models\Cirugia;
use App\Models\ProcedimientoQuirurgico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deriva la plantilla estándar de un procedimiento a partir de lo que de
 * verdad se usó en sus cirugías ya registradas.
 *
 * Es la forma honesta de arrancar: pedirle a un hospital que escriba el
 * protocolo de cero es pedirle el trabajo que la aplicación existe para
 * ahorrarle, y además produce un estándar imaginado. Aquí el estándar sale
 * del histórico —lo que aparece en la mayoría de los casos— y el jefe de
 * quirófanos solo corrige.
 *
 * Criterio: una línea entra a la plantilla si aparece en al menos la mitad
 * de las cirugías analizadas; entra como opcional si aparece en al menos un
 * cuarto. Lo que sale menos que eso es excepción, no protocolo.
 */
class GeneradorPlantilla
{
    /** Presencia mínima para considerar que algo es parte del protocolo. */
    public const UMBRAL_ESTANDAR = 0.5;

    /** Presencia mínima para ofrecerlo como opcional. */
    public const UMBRAL_OPCIONAL = 0.25;

    /** Cirugías mínimas para que la moda signifique algo. */
    public const MINIMO_CIRUGIAS = 3;

    /** Cuántas cirugías respaldarían una propuesta para este procedimiento. */
    public function cirugiasAnalizables(ProcedimientoQuirurgico $procedimiento): int
    {
        return Cirugia::query()
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('hora_fin')
            ->whereHas('procedimientos', fn ($query) => $query
                ->where('procedimientos_quirurgicos.id', $procedimiento->id)
                ->where('cirugia_procedimiento.es_principal', true))
            ->count();
    }

    /**
     * Calcula la plantilla sin escribirla. Devuelve `null` si no hay
     * histórico suficiente para afirmar nada.
     *
     * @return array{insumos: list<array<string, mixed>>, personal: list<array<string, mixed>>, equipos: list<array<string, mixed>>, n_cirugias: int}|null
     */
    public function proponer(ProcedimientoQuirurgico $procedimiento): ?array
    {
        $cirugias = $this->cirugiasDe($procedimiento);
        $n = $cirugias->count();

        if ($n < self::MINIMO_CIRUGIAS) {
            return null;
        }

        return [
            'n_cirugias' => $n,
            'insumos' => $this->insumos($cirugias, $n),
            'personal' => $this->personal($cirugias, $n),
            'equipos' => $this->equipos($cirugias, $n),
        ];
    }

    /**
     * Calcula la plantilla y la escribe, reemplazando la anterior.
     *
     * @return int líneas escritas; 0 si no había histórico suficiente
     */
    public function generar(ProcedimientoQuirurgico $procedimiento): int
    {
        $propuesta = $this->proponer($procedimiento);

        if ($propuesta === null) {
            return 0;
        }

        return DB::transaction(function () use ($procedimiento, $propuesta): int {
            $procedimiento->plantillaInsumos()->delete();
            $procedimiento->plantillaPersonal()->delete();
            $procedimiento->plantillaEquipos()->delete();

            foreach ($propuesta['insumos'] as $fila) {
                $procedimiento->plantillaInsumos()->create($fila);
            }

            foreach ($propuesta['personal'] as $fila) {
                $procedimiento->plantillaPersonal()->create($fila);
            }

            foreach ($propuesta['equipos'] as $fila) {
                $procedimiento->plantillaEquipos()->create($fila);
            }

            return count($propuesta['insumos'])
                + count($propuesta['personal'])
                + count($propuesta['equipos']);
        });
    }

    /**
     * Cirugías realizadas donde este procedimiento fue el principal. Solo
     * las terminadas: una cirugía a medio capturar diría que faltan insumos
     * que en realidad nadie ha registrado todavía.
     *
     * @return Collection<int, Cirugia>
     */
    protected function cirugiasDe(ProcedimientoQuirurgico $procedimiento): Collection
    {
        return Cirugia::query()
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('hora_fin')
            ->whereHas('procedimientos', fn ($query) => $query
                ->where('procedimientos_quirurgicos.id', $procedimiento->id)
                ->where('cirugia_procedimiento.es_principal', true))
            ->with(['consumos', 'equipoQuirurgico', 'equiposMedicos'])
            ->get();
    }

    /**
     * Insumo + fase que aparecen de forma habitual, con la cantidad mediana
     * (no la media: un caso con 30 gasas no debe mover el estándar).
     *
     * @param  Collection<int, Cirugia>  $cirugias
     * @return list<array<string, mixed>>
     */
    protected function insumos(Collection $cirugias, int $n): array
    {
        $porClave = [];

        foreach ($cirugias as $cirugia) {
            // Una cirugía cuenta una sola vez por insumo+fase, aunque la
            // haya cargado en dos líneas.
            $agrupado = [];

            foreach ($cirugia->consumos as $consumo) {
                $clave = $consumo->insumo_id.'|'.$this->valorFase($consumo->fase);
                $agrupado[$clave] = ($agrupado[$clave] ?? 0) + (float) $consumo->cantidad;
            }

            foreach ($agrupado as $clave => $cantidad) {
                $porClave[$clave][] = $cantidad;
            }
        }

        $filas = [];

        foreach ($porClave as $clave => $cantidades) {
            $presencia = count($cantidades) / $n;

            if ($presencia < self::UMBRAL_OPCIONAL) {
                continue;
            }

            [$insumoId, $fase] = explode('|', $clave);

            $filas[] = [
                'insumo_id' => (int) $insumoId,
                'fase' => $fase,
                'cantidad' => $this->habitual($cantidades),
                'opcional' => $presencia < self::UMBRAL_ESTANDAR,
                '_presencia' => $presencia,
            ];
        }

        return $this->ordenar($filas);
    }

    /**
     * Rol + fase con la cantidad habitual de personas. La persona concreta
     * se fija solo si es SIEMPRE la misma en todas las cirugías: en la
     * mayoría de los servicios la define el turno, y grabar un nombre que
     * cambia haría que el digitador tenga que borrarlo cada vez.
     *
     * @param  Collection<int, Cirugia>  $cirugias
     * @return list<array<string, mixed>>
     */
    protected function personal(Collection $cirugias, int $n): array
    {
        $conteos = [];
        $personas = [];
        $minutos = [];

        foreach ($cirugias as $cirugia) {
            $porClave = [];

            foreach ($cirugia->equipoQuirurgico as $miembro) {
                if ($miembro->rol === '') {
                    continue;
                }

                $clave = $miembro->rol.'|'.$this->valorFase($miembro->fase);
                $porClave[$clave] = ($porClave[$clave] ?? 0) + 1;
                $personas[$clave][] = $miembro->recurso_humano_id;
                $minutos[$clave][] = $miembro->minutos_participacion;
            }

            foreach ($porClave as $clave => $cantidad) {
                $conteos[$clave][] = $cantidad;
            }
        }

        $filas = [];

        foreach ($conteos as $clave => $cantidades) {
            $presencia = count($cantidades) / $n;

            if ($presencia < self::UMBRAL_OPCIONAL) {
                continue;
            }

            [$rol, $fase] = explode('|', $clave);

            // Solo se fija la persona si en todas las cirugías fue la misma:
            // en la mayoría de los servicios la define el turno, y grabar un
            // nombre que rota obligaría a borrarlo en cada registro.
            $unicas = array_values(array_unique($personas[$clave] ?? []));
            $siempreLaMisma = count($unicas) === 1;

            $filas[] = [
                'rol' => $rol,
                'fase' => $fase,
                'cantidad' => max(1, (int) round($this->mediana($cantidades))),
                'recurso_humano_id' => $siempreLaMisma ? $unicas[0] : null,
                // Los minutos se dejan libres en la fase quirúrgica: ahí el
                // formulario los toma de los tiempos reales de sala, que
                // siempre son mejores que un estándar.
                'minutos' => $fase === FaseCiclo::Quirurgica->value || ! isset($minutos[$clave])
                    ? null
                    : (int) round($this->mediana($minutos[$clave])),
                'opcional' => $presencia < self::UMBRAL_ESTANDAR,
                '_presencia' => $presencia,
            ];
        }

        return $this->ordenar($filas);
    }

    /**
     * @param  Collection<int, Cirugia>  $cirugias
     * @return list<array<string, mixed>>
     */
    protected function equipos(Collection $cirugias, int $n): array
    {
        $presencias = [];

        foreach ($cirugias as $cirugia) {
            foreach ($cirugia->equiposMedicos->unique('id') as $equipo) {
                $presencias[$equipo->id] = ($presencias[$equipo->id] ?? 0) + 1;
            }
        }

        $filas = [];

        foreach ($presencias as $equipoId => $veces) {
            $presencia = $veces / $n;

            if ($presencia < self::UMBRAL_OPCIONAL) {
                continue;
            }

            $filas[] = [
                'equipo_medico_id' => (int) $equipoId,
                // En blanco = todo el tiempo de sala, que es lo habitual y
                // lo que el formulario ya sabe rellenar.
                'minutos_uso' => null,
                'opcional' => $presencia < self::UMBRAL_ESTANDAR,
                '_presencia' => $presencia,
            ];
        }

        return $this->ordenar($filas);
    }

    /**
     * Lo más habitual primero, y quita la marca auxiliar de presencia.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    protected function ordenar(array $filas): array
    {
        usort($filas, fn (array $a, array $b): int => $b['_presencia'] <=> $a['_presencia']);

        return array_map(function (array $fila): array {
            unset($fila['_presencia']);

            return $fila;
        }, $filas);
    }

    /**
     * La cantidad que más se repite. Para un estándar es mejor que la
     * mediana: con un número par de casos, la mediana inventa valores que
     * nadie usó nunca («4,5 pares de guantes»).
     *
     * @param  list<float|int>  $valores
     */
    protected function habitual(array $valores): float
    {
        if ($valores === []) {
            return 0.0;
        }

        $frecuencias = [];

        foreach ($valores as $valor) {
            $clave = (string) $valor;
            $frecuencias[$clave] = ($frecuencias[$clave] ?? 0) + 1;
        }

        $maxima = max($frecuencias);

        // Empate: el menor de los más frecuentes, para no inflar el estándar.
        // `array_keys` con valor de búsqueda siempre devuelve al menos una
        // clave —$maxima salió de este mismo arreglo—, pero se protege igual.
        $candidatos = array_map('floatval', array_keys($frecuencias, $maxima, true));

        return $candidatos === [] ? 0.0 : min($candidatos);
    }

    /** @param list<float|int> $valores */
    protected function mediana(array $valores): float
    {
        if ($valores === []) {
            return 0.0;
        }

        sort($valores);
        $n = count($valores);
        $medio = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $valores[$medio]
            : ((float) $valores[$medio - 1] + (float) $valores[$medio]) / 2;
    }

    /** La fase puede venir como enum o como string según el modelo. */
    protected function valorFase(mixed $fase): string
    {
        return $fase instanceof FaseCiclo ? $fase->value : (string) $fase;
    }
}
