<?php

namespace App\Enums;

/**
 * Componente del costo TDABC al que se atribuye un exceso.
 *
 * Un sobrecosto sin componente es solo un número grande: obliga a abrir la
 * cirugía y compararla a mano contra las demás. Atribuirlo responde de una
 * vez la única pregunta que importa —¿el exceso fue de insumos, de tiempo o
 * de gente?— y es lo que decide a quién se le pasa la revisión.
 */
enum ComponenteCosto: string
{
    case RecursoHumano = 'recurso_humano';
    case Sala = 'sala';
    case Equipos = 'equipos';
    case Insumos = 'insumos';
    case Indirecto = 'indirecto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Columna de `costos_cirugia` que guarda este componente. */
    public function columna(): string
    {
        return 'costo_'.$this->value;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::RecursoHumano => 'Recurso humano',
            self::Sala => 'Sala operatoria',
            self::Equipos => 'Equipos médicos',
            self::Insumos => 'Insumos',
            self::Indirecto => 'Costos indirectos',
        };
    }

    /**
     * Causas que típicamente explican un exceso en este componente. Se usan
     * para ordenar el selector de la revisión: la lista completa sigue
     * disponible, pero lo probable aparece primero.
     *
     * @return list<CausaSobrecosto>
     */
    public function causasProbables(): array
    {
        return match ($this) {
            self::RecursoHumano => [
                CausaSobrecosto::TiempoQuirurgicoProlongado,
                CausaSobrecosto::PersonalAdicional,
                CausaSobrecosto::ComplicacionClinica,
            ],
            self::Sala => [
                CausaSobrecosto::TiempoQuirurgicoProlongado,
                CausaSobrecosto::RetrasoAlistamiento,
                CausaSobrecosto::ComplicacionClinica,
            ],
            self::Equipos => [
                CausaSobrecosto::EquipoAdicional,
                CausaSobrecosto::TiempoQuirurgicoProlongado,
            ],
            self::Insumos => [
                CausaSobrecosto::ConsumoExcesivoInsumos,
                CausaSobrecosto::ComplicacionClinica,
                CausaSobrecosto::ErrorDeRegistro,
            ],
            // El indirecto es un factor sobre el directo: nunca se desvía solo.
            self::Indirecto => [],
        };
    }
}
