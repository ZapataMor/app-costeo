<?php

namespace App\Rules;

use App\Models\ConceptoCostoIndirecto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Un mismo concepto no puede tener dos vigencias que se pisen.
 *
 * Si «Energía eléctrica» vale 20 millones desde enero y 24 desde julio, en
 * julio hay dos filas candidatas y el costeo tendría que elegir una: cobraría
 * el concepto dos veces o escogería en silencio. Se corta en la captura.
 *
 * Dos rangos se solapan si cada uno empieza antes de que el otro termine.
 * `vigente_hasta` nulo es vigencia abierta, así que cuenta como infinito.
 */
class SinSolapeDeVigencias implements ValidationRule
{
    public function __construct(
        private readonly string $nombre,
        private readonly ?string $vigenteHasta,
        private readonly ?int $ignorarId = null,
    ) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nombre = trim($this->nombre);

        if ($nombre === '' || ! $this->esFecha($value)) {
            return;
        }

        $desde = Carbon::parse($value)->startOfDay();
        $hasta = $this->esFecha($this->vigenteHasta)
            ? Carbon::parse($this->vigenteHasta)->startOfDay()
            : null;

        // Rango invertido: lo reporta la regla `after_or_equal`, no esta.
        if ($hasta !== null && $hasta->lessThan($desde)) {
            return;
        }

        $choque = ConceptoCostoIndirecto::query()
            ->where('nombre', $nombre)
            ->when($this->ignorarId !== null, fn ($q) => $q->whereKeyNot($this->ignorarId))
            // El existente empieza antes de que termine el nuevo…
            ->when($hasta !== null, fn ($q) => $q->whereDate('vigente_desde', '<=', $hasta))
            // …y termina después de que el nuevo empieza.
            ->where(fn ($q) => $q
                ->whereNull('vigente_hasta')
                ->orWhereDate('vigente_hasta', '>=', $desde))
            ->first();

        if ($choque === null) {
            return;
        }

        $fail(sprintf(
            'Ya existe «%s» con vigencia del %s al %s. Cierra esa vigencia antes de abrir una nueva.',
            $choque->nombre,
            $choque->vigente_desde->format('d/m/Y'),
            $choque->vigente_hasta?->format('d/m/Y') ?? 'indefinido',
        ));
    }

    private function esFecha(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '' && strtotime($valor) !== false;
    }
}
