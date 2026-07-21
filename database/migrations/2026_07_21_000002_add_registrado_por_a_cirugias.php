<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autoría del registro. El digitador solo puede ver y corregir lo que él
 * mismo capturó, así que hace falta saberlo en la propia cirugía: deducirlo
 * de la bitácora sería frágil (se puede purgar) y caro de consultar.
 *
 * Nulo en lo sembrado por seeders y en el histórico anterior; al borrar un
 * usuario la cirugía se conserva sin autor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->foreignId('registrado_por')
                ->nullable()
                ->after('hospital_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por');
        });
    }
};
