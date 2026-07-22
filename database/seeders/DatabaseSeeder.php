<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Nota: no usar WithoutModelEvents — los seeders dependen de los eventos
     * de modelo (asignación de tenant y hash del documento).
     *
     * Fuera de producción se añade el DemoSeeder, que monta cirugías, costeo y
     * facturación ficticios sobre el catálogo para poder ver los dashboards
     * llenos. En producción se siembra únicamente lo estructural: hospitales,
     * usuarios y catálogo maestro.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->call([
                HospitalSeeder::class,
                UsuarioSeeder::class,
                CatalogoQuirurgicoSeeder::class,
            ]);

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
