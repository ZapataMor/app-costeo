<?php

namespace App\Enums;

/**
 * Reglas de conocimiento: qué patrón vigila cada una.
 *
 * Las de alcance `cirugia` se evalúan al costear cada caso; las de alcance
 * `periodo` miran un mes completo (un procedimiento, una sala o el hospital)
 * porque solo tienen sentido sobre un volumen.
 *
 * Cada una trae parámetros por defecto razonables para un hospital de
 * mediana complejidad; el hospital los ajusta en la pantalla de reglas.
 */
enum TipoReglaConocimiento: string
{
    case DesviacionEstandar = 'desviacion_estandar';
    case TiempoProlongado = 'tiempo_prolongado';
    case ConsumoFueraProtocolo = 'consumo_fuera_protocolo';
    case MargenNegativo = 'margen_negativo';
    case SalaSubutilizada = 'sala_subutilizada';
    case TasaComplicaciones = 'tasa_complicaciones';
    case TasaCancelacion = 'tasa_cancelacion';
    case CompletitudBaja = 'completitud_baja';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function alcance(): string
    {
        return match ($this) {
            self::DesviacionEstandar,
            self::TiempoProlongado,
            self::ConsumoFueraProtocolo => 'cirugia',
            default => 'periodo',
        };
    }

    public function nombre(): string
    {
        return match ($this) {
            self::DesviacionEstandar => 'Sobrecosto frente al estándar',
            self::TiempoProlongado => 'Tiempo de sala prolongado',
            self::ConsumoFueraProtocolo => 'Consumo fuera de protocolo',
            self::MargenNegativo => 'Procedimiento por debajo de la tarifa',
            self::SalaSubutilizada => 'Sala subutilizada',
            self::TasaComplicaciones => 'Tasa de complicaciones elevada',
            self::TasaCancelacion => 'Tasa de cancelación elevada',
            self::CompletitudBaja => 'Completitud de captura baja',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::DesviacionEstandar => 'Avisa cuando una cirugía cuesta más que su protocolo por encima del umbral de sobrecosto alto del hospital.',
            self::TiempoProlongado => 'Avisa cuando el tiempo de sala supera la duración estimada del procedimiento en más del porcentaje indicado.',
            self::ConsumoFueraProtocolo => 'Avisa cuando una cirugía consume insumos que no están en el protocolo por más del monto indicado.',
            self::MargenNegativo => 'Avisa cuando, en un mes, el costo promedio de un procedimiento supera su tarifa de referencia (SOAT −25 %).',
            self::SalaSubutilizada => 'Avisa cuando una sala usa menos del porcentaje indicado de su capacidad en el mes.',
            self::TasaComplicaciones => 'Avisa cuando la proporción de cirugías con complicación de un procedimiento supera el umbral en el mes.',
            self::TasaCancelacion => 'Avisa cuando se cancela más del porcentaje indicado de las cirugías programadas del mes.',
            self::CompletitudBaja => 'Avisa cuando falta resultado clínico o facturación en más casos de los tolerables en el mes.',
        };
    }

    public function severidadPorDefecto(): SeveridadAlerta
    {
        return match ($this) {
            self::DesviacionEstandar, self::MargenNegativo, self::TasaComplicaciones => SeveridadAlerta::Critica,
            self::CompletitudBaja => SeveridadAlerta::Informativa,
            default => SeveridadAlerta::Advertencia,
        };
    }

    /**
     * Parámetros por defecto, con la descripción de cada uno para el
     * formulario de la regla.
     *
     * @return array<string, array{valor: float|int, etiqueta: string, unidad: string}>
     */
    public function parametrosPorDefecto(): array
    {
        return match ($this) {
            self::DesviacionEstandar => [
                'exceso_minimo_cop' => ['valor' => 50_000, 'etiqueta' => 'Exceso mínimo para avisar', 'unidad' => 'COP'],
            ],
            self::TiempoProlongado => [
                'exceso_pct' => ['valor' => 50, 'etiqueta' => 'Exceso sobre la duración estimada', 'unidad' => '%'],
            ],
            self::ConsumoFueraProtocolo => [
                'monto_minimo_cop' => ['valor' => 80_000, 'etiqueta' => 'Consumo fuera de protocolo mínimo', 'unidad' => 'COP'],
            ],
            self::MargenNegativo => [
                'minimo_casos' => ['valor' => 3, 'etiqueta' => 'Casos mínimos en el mes', 'unidad' => 'cirugías'],
                'perdida_minima_pct' => ['valor' => 5, 'etiqueta' => 'Pérdida mínima sobre la tarifa', 'unidad' => '%'],
            ],
            self::SalaSubutilizada => [
                'utilizacion_minima_pct' => ['valor' => 30, 'etiqueta' => 'Utilización mínima esperada', 'unidad' => '%'],
            ],
            self::TasaComplicaciones => [
                'tasa_maxima_pct' => ['valor' => 10, 'etiqueta' => 'Tasa máxima tolerable', 'unidad' => '%'],
                'minimo_casos' => ['valor' => 5, 'etiqueta' => 'Casos mínimos en el mes', 'unidad' => 'cirugías'],
            ],
            self::TasaCancelacion => [
                'tasa_maxima_pct' => ['valor' => 5, 'etiqueta' => 'Tasa máxima tolerable', 'unidad' => '%'],
            ],
            self::CompletitudBaja => [
                'completitud_minima_pct' => ['valor' => 90, 'etiqueta' => 'Completitud mínima', 'unidad' => '%'],
            ],
        };
    }
}
