<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de costos indirectos por bolsa e inductor (corte 2 del módulo CIF).
 *
 * Sustituye —cuando el hospital lo active— el `factor_indirecto` único por
 * conceptos con su propio monto mensual, base de asignación y vigencia.
 *
 * Los conceptos NACEN INACTIVOS y solo `ActivarCategoriaCif` puede
 * encenderlos: activar una bolsa excluye del costo directo el componente
 * equivalente, y mientras el motor de asignación no exista (corte 3) eso
 * restaría costo sin sustituirlo. Las columnas `origen_*` de `hospitales`
 * y el flag `activo` viajan siempre juntos en la misma transacción, así que
 * un hospital nunca queda con el componente directo y la bolsa a la vez.
 *
 * Nada cambia en el costeo al aplicar esta migración: sin conceptos activos
 * el resultado sigue siendo `costo_directo × factor_indirecto`.
 */
return new class extends Migration
{
    /** Componentes del costo directo que una bolsa CIF puede reemplazar. */
    private const ORIGENES = [
        'origen_infraestructura',
        'origen_depreciacion_equipos',
        'origen_personal_indirecto',
    ];

    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            foreach (self::ORIGENES as $columna) {
                $table->string($columna, 20)->default('digitado');
            }
        });

        Schema::create('conceptos_costo_indirecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('nombre');
            $table->string('categoria', 30);
            $table->string('base_asignacion', 30);

            // Excluyentes por construcción: `porcentaje_directo` usa el
            // porcentaje y las bases por minuto el monto mensual. La regla
            // vive en el Form Request; aquí ambos son nullable.
            $table->decimal('monto_mensual', 14, 2)->nullable();
            $table->decimal('porcentaje', 8, 4)->nullable();

            // `vigente_hasta` null = vigencia abierta.
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->boolean('activo')->default(false);

            // Misma trazabilidad académica que el resto de la Capa 1.
            $table->string('fuente')->nullable();
            $table->string('nivel_confiabilidad', 20)->default('estimado');
            $table->timestamps();

            // Nombres explícitos: el que genera Laravel a partir de esta
            // tabla y estas columnas pasa de los 64 caracteres que admite
            // MySQL como identificador.
            //
            // Resuelve «conceptos vigentes de esta categoría» sin escanear.
            $table->index(['hospital_id', 'categoria', 'vigente_desde'], 'cci_hospital_categoria_vigencia_idx');
            // El solape de vigencias se comprueba entre filas del mismo
            // nombre dentro del hospital.
            $table->index(['hospital_id', 'nombre'], 'cci_hospital_nombre_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_costo_indirecto');

        Schema::table('hospitales', function (Blueprint $table) {
            $table->dropColumn(self::ORIGENES);
        });
    }
};
