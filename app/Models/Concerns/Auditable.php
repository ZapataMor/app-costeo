<?php

namespace App\Models\Concerns;

use App\Models\RegistroActividad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en la bitácora (Historial) toda creación, actualización y
 * eliminación del modelo, con el usuario autenticado y la hora.
 * Solo audita cuando hay un usuario en sesión: los seeders y procesos
 * de consola no generan ruido en el historial.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            static::auditar('creó', $model);
        });

        static::updated(function (Model $model): void {
            static::auditar('actualizó', $model);
        });

        static::deleted(function (Model $model): void {
            static::auditar('eliminó', $model);
        });
    }

    protected static function auditar(string $accion, Model $model): void
    {
        if (Auth::guest()) {
            return;
        }

        RegistroActividad::registrar(
            $accion,
            sprintf('%s %s «%s»', ucfirst($accion), static::etiquetaAuditable(), $model->nombreAuditable()),
            $model,
        );
    }

    /** Etiqueta en español del modelo para la descripción del historial. */
    protected static function etiquetaAuditable(): string
    {
        return match (class_basename(static::class)) {
            'RecursoHumano' => 'el recurso humano',
            'Insumo' => 'el insumo',
            'EquipoMedico' => 'el equipo médico',
            'SalaOperatoria' => 'la sala operatoria',
            'ProcedimientoQuirurgico' => 'el procedimiento quirúrgico',
            'Paciente' => 'el paciente',
            'Cirugia' => 'la cirugía',
            'CostoCirugia' => 'el costo de cirugía',
            'ResultadoClinico' => 'el resultado clínico',
            'Facturacion' => 'la facturación',
            'Hospital' => 'la configuración del hospital',
            default => 'el registro de '.class_basename(static::class),
        };
    }

    /** Identificador legible del registro para la descripción. */
    public function nombreAuditable(): string
    {
        /** @var Model $this */
        // Pacientes: nombre completo + id. NUNCA el documento: está cifrado
        // en la BD y aquí quedaría en texto plano dentro del historial.
        if ($this->getAttribute('nombres') !== null) {
            return trim($this->getAttribute('nombres').' '.$this->getAttribute('apellidos'))
                .' (#'.$this->getKey().')';
        }

        return (string) ($this->getAttribute('nombre')
            ?? $this->getAttribute('codigo')
            ?? '#'.$this->getKey());
    }
}
