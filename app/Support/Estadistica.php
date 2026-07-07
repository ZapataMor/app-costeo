<?php

namespace App\Support;

/**
 * Funciones estadísticas usadas por el detector de outliers y los KPIs.
 */
class Estadistica
{
    /**
     * @param  array<int, float>  $valores
     */
    public static function media(array $valores): float
    {
        $n = count($valores);

        return $n > 0 ? array_sum($valores) / $n : 0.0;
    }

    /**
     * Desviación estándar muestral (n − 1).
     *
     * @param  array<int, float>  $valores
     */
    public static function desviacionEstandar(array $valores): float
    {
        $n = count($valores);

        if ($n < 2) {
            return 0.0;
        }

        $media = self::media($valores);
        $sumaCuadrados = array_sum(array_map(
            fn (float $v): float => ($v - $media) ** 2,
            $valores,
        ));

        return sqrt($sumaCuadrados / ($n - 1));
    }

    /**
     * Coeficiente de variación (desviación ÷ media). Null si la media es 0.
     *
     * @param  array<int, float>  $valores
     */
    public static function coeficienteVariacion(array $valores): ?float
    {
        $media = self::media($valores);

        if ($media == 0.0) {
            return null;
        }

        return self::desviacionEstandar($valores) / $media;
    }

    /**
     * Percentil con interpolación lineal (método inclusivo, como Excel).
     *
     * @param  array<int, float>  $valores
     * @param  float  $p  entre 0 y 1
     */
    public static function percentil(array $valores, float $p): float
    {
        $n = count($valores);

        if ($n === 0) {
            return 0.0;
        }

        sort($valores);
        $posicion = ($n - 1) * $p;
        $inferior = (int) floor($posicion);
        $fraccion = $posicion - $inferior;

        if ($inferior + 1 >= $n) {
            return $valores[$n - 1];
        }

        return $valores[$inferior] + $fraccion * ($valores[$inferior + 1] - $valores[$inferior]);
    }
}
