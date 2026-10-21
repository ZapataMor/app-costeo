<?php

namespace App\Enums;

/**
 * Cómo quedó el costo real de una cirugía frente a su costo estándar.
 *
 * Es la clasificación del pipeline doctoral (Guía de campos TDABC+MILP):
 * Subejecución ≤ −5 %, En rango, Sobrecosto leve 5–20 %, Sobrecosto alto
 * > 20 %. Los umbrales viven en el hospital para poder ajustarlos sin tocar
 * código mientras el profesor los confirma.
 *
 * La subejecución no es una buena noticia por defecto: un costo muy por
 * debajo del protocolo casi siempre es captura incompleta (insumos o
 * personal sin registrar), no ahorro.
 */
enum ClasificacionDesviacion: string
{
    case Subejecucion = 'subejecucion';
    case EnRango = 'en_rango';
    case SobrecostoLeve = 'sobrecosto_leve';
    case SobrecostoAlto = 'sobrecosto_alto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Umbrales en proporción (−0,05 = −5 %). El límite inferior de cada
     * banda es inclusivo: un 5 % exacto ya es sobrecosto leve.
     */
    public static function clasificar(float $desviacionPct, float $subejecucion, float $leve, float $alto): self
    {
        return match (true) {
            $desviacionPct <= $subejecucion => self::Subejecucion,
            $desviacionPct > $alto => self::SobrecostoAlto,
            $desviacionPct >= $leve => self::SobrecostoLeve,
            default => self::EnRango,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Subejecucion => 'Subejecución',
            self::EnRango => 'En rango',
            self::SobrecostoLeve => 'Sobrecosto leve',
            self::SobrecostoAlto => 'Sobrecosto alto',
        };
    }

    /**
     * Catálogo para filtros y leyendas.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function catalogo(): array
    {
        return array_map(fn (self $c): array => [
            'valor' => $c->value,
            'etiqueta' => $c->etiqueta(),
        ], self::cases());
    }
}
