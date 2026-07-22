<?php

namespace App\Enums;

/**
 * Fase del ciclo quirúrgico a la que se atribuye un consumo o una
 * participación de personal.
 *
 * Sin esta atribución todo el gasto cae en la cirugía como un bloque y no se
 * puede responder cuánto cuesta preparar al paciente frente a operarlo o
 * recuperarlo, que es el objeto de la separación en fases.
 *
 * La consulta de valoración previa NO pertenece a `Prequirurgica`: es un
 * objeto de costo distinto —se factura aparte y no siempre termina en
 * cirugía—, y meterla aquí encarecería artificialmente los procedimientos.
 */
enum FaseCiclo: string
{
    case Prequirurgica = 'pre';
    case Quirurgica = 'quirurgica';
    case Postquirurgica = 'post';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Prequirurgica => 'Pre-quirúrgica',
            self::Quirurgica => 'Quirúrgica',
            self::Postquirurgica => 'Post-quirúrgica',
        };
    }
}
