<?php

namespace App\Enums;

/**
 * De dónde sale un componente de costo que puede venir por dos vías.
 *
 * `salas_operatorias.costo_hora`, `equipos_medicos.costo_hora` y
 * `recursos_humanos.costos_indirectos_mensuales` ya contienen costos
 * indirectos de forma implícita. Si además se crea una bolsa CIF de la
 * categoría equivalente, el mismo costo se cuenta dos veces.
 *
 * En vez de forzar esos campos a cero —que destruye un dato recolectado y
 * vuelve mentira su `fuente`/`nivel_confiabilidad`— el hospital marca el
 * origen del componente y el motor excluye del costo directo lo que ya
 * cubre la bolsa. El valor digitado sigue ahí para comparar.
 */
enum OrigenComponente: string
{
    /** El campo del catálogo manda; el costo entra al directo. */
    case Digitado = 'digitado';

    /** Lo cubre una bolsa CIF; el campo se excluye del costo directo. */
    case DerivadoDeCif = 'derivado_de_cif';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
