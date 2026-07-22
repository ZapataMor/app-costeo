<?php

namespace Database\Seeders\Concerns;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Salida por consola desde un seeder, tolerante a que no haya comando.
 *
 * `Seeder::$command` solo queda asignado cuando el seeder corre vía `db:seed`.
 * Si se instancia a mano (como hace DemoSeeder con el catálogo) nunca se
 * asigna, y su PHPDoc lo declara no-nulo, así que leerlo directamente no es
 * seguro. Aquí guardamos nuestra propia referencia, honestamente nullable.
 *
 * @phpstan-require-extends Seeder
 */
trait InformaEnConsola
{
    protected ?Command $consola = null;

    /**
     * @return $this
     */
    public function setCommand(Command $command)
    {
        $this->consola = $command;

        return parent::setCommand($command);
    }

    /**
     * Comando activo, para propagarlo a seeders instanciados a mano.
     */
    public function consola(): ?Command
    {
        return $this->consola;
    }

    protected function informar(string $mensaje): void
    {
        $this->consola?->info($mensaje);
    }

    protected function advertir(string $mensaje): void
    {
        $this->consola?->warn($mensaje);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     */
    protected function tabla(array $encabezados, array $filas): void
    {
        if ($this->consola === null) {
            return;
        }

        $this->consola->newLine();
        $this->consola->table($encabezados, $filas);
        $this->consola->newLine();
    }
}
