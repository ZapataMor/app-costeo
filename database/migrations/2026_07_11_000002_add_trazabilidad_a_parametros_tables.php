<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad académica de los parámetros de Capa 1: de dónde salió el
 * dato (fuente) y qué tan confiable es (medido | estimado | supuesto).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablas = [
        'recursos_humanos',
        'insumos',
        'equipos_medicos',
        'salas_operatorias',
        'procedimientos_quirurgicos',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('fuente')->nullable();
                $table->string('nivel_confiabilidad', 20)->default('estimado');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['fuente', 'nivel_confiabilidad']);
            });
        }
    }
};
