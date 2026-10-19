<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El enum NivelComplejidad usa `media`, pero la columna nació con default
 * `mediana` y los seeders lo repetían. Con el CRUD de instituciones el valor
 * pasa a elegirse en un desplegable, así que se unifica al enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('hospitales')->where('nivel_complejidad', 'mediana')->update(['nivel_complejidad' => 'media']);

        Schema::table('hospitales', function (Blueprint $table) {
            $table->string('nivel_complejidad', 20)->default('media')->change();
        });
    }

    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->string('nivel_complejidad', 20)->default('mediana')->change();
        });

        DB::table('hospitales')->where('nivel_complejidad', 'media')->update(['nivel_complejidad' => 'mediana']);
    }
};
