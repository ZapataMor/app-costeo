<?php

namespace App\Exceptions;

use App\Enums\CategoriaCif;
use RuntimeException;

/**
 * Activar una bolsa CIF cuyo componente directo equivalente sigue digitado
 * contaría el mismo costo dos veces.
 *
 * No es un error del usuario sino una decisión que debe tomar: o marca el
 * componente como derivado de CIF —conservando los valores digitados para
 * comparar— o los ajusta a cero a mano. Por eso la excepción transporta los
 * registros en conflicto: el mensaje tiene que nombrarlos, no decir
 * «hay un conflicto».
 */
class SolapeDeCostoIndirectoException extends RuntimeException
{
    /**
     * @param  list<array{nombre: string, valor: float}>  $conflictos
     */
    public function __construct(
        public readonly CategoriaCif $categoria,
        public readonly array $conflictos,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    /**
     * @param  list<array{nombre: string, valor: float}>  $conflictos
     */
    public static function porComponenteDigitado(CategoriaCif $categoria, array $conflictos): self
    {
        $detalle = implode(', ', array_map(
            static fn (array $c): string => sprintf(
                '%s: $%s',
                $c['nombre'],
                number_format($c['valor'], 0, ',', '.'),
            ),
            array_slice($conflictos, 0, 5),
        ));

        $sobrantes = count($conflictos) - 5;

        return new self($categoria, $conflictos, sprintf(
            'No se puede activar la bolsa de «%s»: %d registro(s) activo(s) todavía tienen %s digitado (%s%s). '
            .'Esos valores ya incluyen el costo que cubriría la bolsa, así que se contaría dos veces. '
            .'Marca el componente como derivado de CIF —los valores digitados se conservan para comparación— '
            .'o ajústalos a cero manualmente.',
            $categoria->value,
            count($conflictos),
            $categoria->descripcionDelSolape() ?? 'el componente equivalente',
            $detalle,
            $sobrantes > 0 ? sprintf(' y %d más', $sobrantes) : '',
        ));
    }
}
