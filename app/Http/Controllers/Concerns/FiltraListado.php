<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Búsqueda y filtros de los listados de catálogo.
 *
 * Los catálogos de Capa 1 crecen a cientos de filas (insumos, procedimientos)
 * y paginar de 15 en 15 sin buscador los volvía inutilizables.
 */
trait FiltraListado
{
    /**
     * Aplica `?q=` sobre las columnas indicadas (OR entre ellas) y los
     * filtros exactos `?clave=valor` que vengan en la petición.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @param  list<string>  $columnasBusqueda
     * @param  array<string, string>  $filtrosExactos  parámetro de la URL => columna
     * @return Builder<TModel>
     */
    protected function aplicarFiltros(
        Builder $consulta,
        Request $request,
        array $columnasBusqueda,
        array $filtrosExactos = [],
    ): Builder {
        $busqueda = trim((string) $request->string('q'));

        if ($busqueda !== '' && $columnasBusqueda !== []) {
            $consulta->where(function (Builder $consulta) use ($columnasBusqueda, $busqueda): void {
                foreach ($columnasBusqueda as $columna) {
                    $consulta->orWhere($columna, 'like', "%{$busqueda}%");
                }
            });
        }

        foreach ($filtrosExactos as $parametro => $columna) {
            $valor = trim((string) $request->string($parametro));

            if ($valor === '') {
                continue;
            }

            // Los filtros booleanos llegan como '1'/'0' desde el desplegable.
            $consulta->where($columna, match ($valor) {
                '1' => true,
                '0' => false,
                default => $valor,
            });
        }

        return $consulta;
    }

    /**
     * Valores actuales de los filtros, para repintar la barra de búsqueda.
     *
     * @param  list<string>  $claves
     * @return array<string, string>
     */
    protected function filtrosActivos(Request $request, array $claves): array
    {
        return collect(['q', ...$claves])
            ->mapWithKeys(fn (string $clave): array => [
                $clave => trim((string) $request->string($clave)),
            ])
            ->all();
    }
}
