<?php

namespace App\Enums;

/**
 * Recomendaciones que el histórico permite derivar (minería descriptiva,
 * no predictiva: lo predictivo es Nivel 5 de madurez en la tesis).
 */
enum TipoRecomendacion: string
{
    case EstandarizarInsumo = 'estandarizar_insumo';
    case AjustarProtocolo = 'ajustar_protocolo';
    case RenegociarTarifa = 'renegociar_tarifa';
    case ReducirTiempo = 'reducir_tiempo';
    case ReasignarCapacidad = 'reasignar_capacidad';
    case RevisionEntrePares = 'revision_entre_pares';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EstandarizarInsumo => 'Estandarizar consumo de insumo',
            self::AjustarProtocolo => 'Ajustar protocolo',
            self::RenegociarTarifa => 'Renegociar tarifa',
            self::ReducirTiempo => 'Reducir tiempo de sala',
            self::ReasignarCapacidad => 'Reasignar capacidad de sala',
            self::RevisionEntrePares => 'Revisión entre pares',
        };
    }

    /** @return list<array{valor: string, etiqueta: string}> */
    public static function catalogo(): array
    {
        return array_map(fn (self $c): array => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()], self::cases());
    }
}
