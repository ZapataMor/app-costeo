<?php

namespace App\Enums;

enum CategoriaLeccion: string
{
    case Costos = 'costos';
    case Clinica = 'clinica';
    case Proceso = 'proceso';
    case Seguridad = 'seguridad';
    case Protocolo = 'protocolo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Costos => 'Costos e insumos',
            self::Clinica => 'Práctica clínica',
            self::Proceso => 'Proceso y logística',
            self::Seguridad => 'Seguridad del paciente',
            self::Protocolo => 'Protocolo quirúrgico',
        };
    }

    /** @return list<array{valor: string, etiqueta: string}> */
    public static function catalogo(): array
    {
        return array_map(fn (self $c): array => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()], self::cases());
    }
}
