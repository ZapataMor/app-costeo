<?php

namespace App\Enums;

/**
 * Causa con la que un revisor explica un sobrecosto detectado.
 *
 * Este catálogo es la bisagra entre la Capa 3 y la Capa 4: la estadística
 * detecta el caso atípico, pero solo una persona sabe *por qué* pasó. Al
 * obligar a elegir de una lista cerrada —en vez de un texto libre— ese
 * conocimiento tácito queda contable: se puede sumar, comparar entre
 * procedimientos y convertir en lección aprendida.
 *
 * La distinción que hace útil el catálogo es `evitable()`. Un sobrecosto por
 * complicación clínica no es una falla de gestión y perseguirlo desgasta al
 * equipo; uno por desperdicio de insumos o por retraso de alistamiento sí es
 * recuperable. Sin esa separación, el total de sobrecostos es una cifra que
 * nadie puede accionar.
 */
enum CausaSobrecosto: string
{
    case ConsumoExcesivoInsumos = 'consumo_excesivo_insumos';
    case TiempoQuirurgicoProlongado = 'tiempo_quirurgico_prolongado';
    case RetrasoAlistamiento = 'retraso_alistamiento';
    case EquipoAdicional = 'equipo_adicional';
    case PersonalAdicional = 'personal_adicional';
    case ComplicacionClinica = 'complicacion_clinica';
    case CasoComplejoJustificado = 'caso_complejo_justificado';
    case ErrorDeRegistro = 'error_de_registro';
    case Otra = 'otra';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConsumoExcesivoInsumos => 'Consumo excesivo o desperdicio de insumos',
            self::TiempoQuirurgicoProlongado => 'Tiempo quirúrgico prolongado',
            self::RetrasoAlistamiento => 'Retraso de alistamiento o sala parada',
            self::EquipoAdicional => 'Equipo médico adicional no previsto',
            self::PersonalAdicional => 'Personal adicional no previsto',
            self::ComplicacionClinica => 'Complicación clínica intra o postoperatoria',
            self::CasoComplejoJustificado => 'Caso complejo justificado',
            self::ErrorDeRegistro => 'Error de registro (el dato está mal capturado)',
            self::Otra => 'Otra',
        };
    }

    /**
     * ¿El sobrecosto era evitable con gestión? Es lo que separa la pérdida
     * recuperable del costo legítimo de atender un caso difícil.
     */
    public function evitable(): bool
    {
        return match ($this) {
            self::ConsumoExcesivoInsumos,
            self::TiempoQuirurgicoProlongado,
            self::RetrasoAlistamiento,
            self::EquipoAdicional,
            self::PersonalAdicional => true,

            // La complicación y el caso complejo son costo legítimo; el error
            // de registro no es un sobrecosto en absoluto, es un dato sucio y
            // por eso tampoco suma a la pérdida evitable.
            self::ComplicacionClinica,
            self::CasoComplejoJustificado,
            self::ErrorDeRegistro,
            self::Otra => false,
        };
    }

    /**
     * Catálogo para el selector del formulario de revisión.
     *
     * @return list<array{valor: string, etiqueta: string, evitable: bool}>
     */
    public static function catalogo(): array
    {
        return array_map(fn (self $causa): array => [
            'valor' => $causa->value,
            'etiqueta' => $causa->etiqueta(),
            'evitable' => $causa->evitable(),
        ], self::cases());
    }
}
