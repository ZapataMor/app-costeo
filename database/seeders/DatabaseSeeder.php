<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Nota: no usar WithoutModelEvents — el DemoSeeder depende de los
     * eventos de modelo (asignación de tenant y hash del documento).
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
