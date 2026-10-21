<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa 4 — Conocimiento (tesis, capítulo 5: comunidades de práctica,
 * lecciones aprendidas, mejores prácticas, minería de datos, gestión de
 * protocolos; cotización: reglas, alertas, recomendaciones).
 *
 *   reglas_conocimiento   umbrales que cada hospital ajusta (una por tipo)
 *   alertas_conocimiento  lo que las reglas detectan, con impacto en COP
 *   lecciones_aprendidas  conocimiento tácito hecho explícito, con ciclo
 *                         borrador → revisión → publicada (y red opcional)
 *   recomendaciones       acciones derivadas del histórico, con ahorro
 *                         estimado y seguimiento hasta implementarlas
 *   comunidades_practica  foros por especialidad, hilos enlazados a casos
 *
 * Y el usuario `profesional`: personal quirúrgico con cuenta propia,
 * vinculado a su registro de recurso humano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('recurso_humano_id')->nullable()->after('hospital_id')
                ->constrained('recursos_humanos')->nullOnDelete();
        });

        Schema::create('reglas_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('severidad', 15)->default('advertencia');
            $table->json('parametros');
            $table->boolean('activa')->default(true);
            $table->timestamp('evaluada_en')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'tipo']);
        });

        Schema::create('alertas_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->foreignId('regla_conocimiento_id')->nullable()->constrained('reglas_conocimiento')->nullOnDelete();
            $table->string('tipo', 40);
            $table->string('severidad', 15);
            $table->string('titulo');
            $table->text('descripcion');
            $table->foreignId('cirugia_id')->nullable()->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('procedimiento_quirurgico_id')->nullable()->constrained('procedimientos_quirurgicos')->nullOnDelete();
            $table->foreignId('sala_operatoria_id')->nullable()->constrained('salas_operatorias')->nullOnDelete();
            $table->foreignId('recurso_humano_id')->nullable()->constrained('recursos_humanos')->nullOnDelete();
            // Mes evaluado (Y-m) en las reglas agregadas; null en las de una cirugía.
            $table->string('periodo', 7)->nullable();
            $table->decimal('valor', 16, 4)->nullable();
            $table->decimal('umbral', 16, 4)->nullable();
            $table->decimal('impacto_cop', 14, 2)->nullable();
            $table->json('datos')->nullable();
            // Identidad del hallazgo (tipo + objeto + periodo): reevaluar
            // actualiza la alerta en vez de duplicarla.
            $table->string('clave', 120);
            $table->string('estado', 15)->default('pendiente');
            $table->string('causa', 40)->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'clave']);
            $table->index(['hospital_id', 'estado']);
            $table->index(['hospital_id', 'tipo']);
        });

        Schema::create('lecciones_aprendidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('tipo', 20)->default('leccion');
            $table->string('titulo');
            $table->text('contexto');
            $table->text('leccion');
            $table->text('recomendacion')->nullable();
            $table->string('categoria', 20);
            $table->string('causa', 40)->nullable();
            $table->foreignId('procedimiento_quirurgico_id')->nullable()->constrained('procedimientos_quirurgicos')->nullOnDelete();
            $table->string('especialidad')->nullable();
            $table->foreignId('alerta_sobrecosto_id')->nullable()->constrained('alertas_sobrecosto')->nullOnDelete();
            $table->foreignId('alerta_conocimiento_id')->nullable()->constrained('alertas_conocimiento')->nullOnDelete();
            $table->decimal('impacto_estimado_cop', 14, 2)->nullable();
            $table->string('estado', 15)->default('borrador');
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->text('motivo_devolucion')->nullable();
            $table->timestamp('publicada_en')->nullable();
            // Publicada a la red departamental: visible para los demás
            // hospitales, sin personas ni pacientes.
            $table->boolean('compartida_red')->default(false);
            $table->timestamp('compartida_en')->nullable();
            $table->json('etiquetas')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'estado']);
            $table->index(['compartida_red', 'publicada_en']);
        });

        Schema::create('leccion_cirugia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leccion_aprendida_id')->constrained('lecciones_aprendidas')->cascadeOnDelete();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['leccion_aprendida_id', 'cirugia_id']);
        });

        Schema::create('leccion_valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leccion_aprendida_id')->constrained('lecciones_aprendidas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['leccion_aprendida_id', 'user_id']);
        });

        Schema::create('recomendaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo');
            $table->text('descripcion');
            $table->foreignId('procedimiento_quirurgico_id')->nullable()->constrained('procedimientos_quirurgicos')->nullOnDelete();
            $table->foreignId('insumo_id')->nullable()->constrained('insumos')->nullOnDelete();
            $table->foreignId('sala_operatoria_id')->nullable()->constrained('salas_operatorias')->nullOnDelete();
            $table->foreignId('recurso_humano_id')->nullable()->constrained('recursos_humanos')->nullOnDelete();
            $table->decimal('ahorro_mensual_estimado', 14, 2)->default(0);
            $table->json('evidencia')->nullable();
            $table->string('clave', 120);
            $table->string('estado', 15)->default('nueva');
            $table->text('nota')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_compromiso')->nullable();
            $table->foreignId('gestionada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gestionada_en')->nullable();
            $table->timestamp('implementada_en')->nullable();
            $table->foreignId('leccion_aprendida_id')->nullable()->constrained('lecciones_aprendidas')->nullOnDelete();
            $table->timestamp('generada_en');
            $table->timestamps();

            $table->unique(['hospital_id', 'clave']);
            $table->index(['hospital_id', 'estado']);
        });

        Schema::create('comunidades_practica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('nombre');
            $table->string('especialidad')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'nombre']);
        });

        Schema::create('comunidad_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunidad_practica_id')->constrained('comunidades_practica')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol', 15)->default('miembro');
            $table->timestamps();

            $table->unique(['comunidad_practica_id', 'user_id']);
        });

        Schema::create('hilos_comunidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunidad_practica_id')->constrained('comunidades_practica')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo');
            $table->text('cuerpo');
            // Lo que se discute: una lección, una alerta, un procedimiento o
            // una recomendación. Opcional.
            $table->string('enlazable_tipo', 30)->nullable();
            $table->unsignedBigInteger('enlazable_id')->nullable();
            $table->boolean('fijado')->default(false);
            $table->boolean('cerrado')->default(false);
            $table->foreignId('leccion_aprendida_id')->nullable()->constrained('lecciones_aprendidas')->nullOnDelete();
            $table->timestamp('ultima_actividad_en');
            $table->timestamps();

            $table->index(['comunidad_practica_id', 'ultima_actividad_en']);
        });

        Schema::create('respuestas_hilo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hilo_comunidad_id')->constrained('hilos_comunidad')->cascadeOnDelete();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cuerpo');
            $table->boolean('es_solucion')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_hilo');
        Schema::dropIfExists('hilos_comunidad');
        Schema::dropIfExists('comunidad_miembros');
        Schema::dropIfExists('comunidades_practica');
        Schema::dropIfExists('recomendaciones');
        Schema::dropIfExists('leccion_valoraciones');
        Schema::dropIfExists('leccion_cirugia');
        Schema::dropIfExists('lecciones_aprendidas');
        Schema::dropIfExists('alertas_conocimiento');
        Schema::dropIfExists('reglas_conocimiento');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurso_humano_id');
        });
    }
};
