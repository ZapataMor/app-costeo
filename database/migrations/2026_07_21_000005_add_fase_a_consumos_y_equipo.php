<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuye cada consumo de insumo y cada participación de personal a una fase
 * del ciclo. Sin esto, la bata que se le pone al paciente antes de entrar y
 * la sutura que se usa operando son indistinguibles, y el costo por fase no
 * se puede calcular.
 *
 * Las restricciones `unique` tienen que incorporar la fase: el instrumentador
 * que alista la sala y luego opera son dos participaciones distintas, y la
 * misma gasa puede consumirse en preparación y en cirugía.
 *
 * En MySQL esos índices sostienen las claves foráneas de `cirugia_id`, así
 * que hay que soltarlas antes de reemplazarlos y volver a crearlas después.
 * SQLite (los tests) reconstruye la tabla y no necesita ese rodeo.
 *
 * Lo ya registrado se da por quirúrgico, que es lo que de hecho era: hasta
 * ahora el formulario solo capturaba el acto quirúrgico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumo_insumos', function (Blueprint $table) {
            $table->string('fase', 12)->default('quirurgica')->after('insumo_id');
        });

        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->string('fase', 12)->default('quirurgica')->after('rol');
        });

        $this->reemplazarUnico(
            'consumo_insumos',
            ['cirugia_id', 'insumo_id'],
            ['cirugia_id', 'insumo_id', 'fase'],
            'consumo_insumo_fase_unico',
        );

        $this->reemplazarUnico(
            'miembros_equipo_quirurgico',
            'miembro_equipo_unico',
            ['cirugia_id', 'recurso_humano_id', 'rol', 'fase'],
            'miembro_equipo_fase_unico',
        );
    }

    public function down(): void
    {
        $this->reemplazarUnico(
            'consumo_insumos',
            'consumo_insumo_fase_unico',
            ['cirugia_id', 'insumo_id'],
            null,
        );

        $this->reemplazarUnico(
            'miembros_equipo_quirurgico',
            'miembro_equipo_fase_unico',
            ['cirugia_id', 'recurso_humano_id', 'rol'],
            'miembro_equipo_unico',
        );

        Schema::table('consumo_insumos', function (Blueprint $table) {
            $table->dropColumn('fase');
        });

        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->dropColumn('fase');
        });
    }

    /**
     * Cambia un índice único que la clave foránea de `cirugia_id` está usando.
     *
     * @param  string|list<string>  $anterior  columnas o nombre del índice a eliminar
     * @param  list<string>  $nuevas  columnas del índice nuevo
     */
    protected function reemplazarUnico(string $tabla, string|array $anterior, array $nuevas, ?string $nombre): void
    {
        $enMysql = DB::connection()->getDriverName() === 'mysql';

        if ($enMysql) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['cirugia_id']);
            });
        }

        Schema::table($tabla, function (Blueprint $table) use ($anterior, $nuevas, $nombre) {
            $table->dropUnique($anterior);
            $table->unique($nuevas, $nombre);
        });

        if ($enMysql) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreign('cirugia_id')->references('id')->on('cirugias')->cascadeOnDelete();
            });
        }
    }
};
