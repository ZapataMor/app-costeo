<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación de listados a CSV.
 *
 * Detalles pensados para Excel en español:
 *  - separador `;` (con `,` decimal, la coma parte las celdas)
 *  - BOM UTF-8, sin el cual Excel rompe las tildes y las eñes
 *  - streaming, para no cargar en memoria un histórico completo
 */
class ExportadorCsv
{
    public const SEPARADOR = ';';

    /**
     * @param  list<string>  $encabezados
     * @param  iterable<int, list<scalar|null>>  $filas
     */
    public static function descargar(string $nombreArchivo, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($encabezados, $filas): void {
                $salida = fopen('php://output', 'w');

                // BOM: sin él Excel interpreta el archivo como ANSI.
                fwrite($salida, "\xEF\xBB\xBF");

                fputcsv($salida, $encabezados, self::SEPARADOR);

                foreach ($filas as $fila) {
                    fputcsv($salida, $fila, self::SEPARADOR);
                }

                fclose($salida);
            },
            $nombreArchivo,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /** Nombre de archivo con el hospital y la fecha, para no pisar descargas. */
    public static function nombre(string $base, ?string $sufijo = null): string
    {
        return collect([$base, $sufijo, now()->format('Y-m-d')])
            ->filter()
            ->map(fn (string $parte): string => Str::slug($parte))
            ->implode('_').'.csv';
    }
}
