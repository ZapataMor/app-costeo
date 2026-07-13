<?php

use App\Models\Paciente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correcciones de integridad del modelo de costeo:
 *
 * 1. El estado por defecto de una cirugía pasa de 'realizada' a 'en_proceso':
 *    nada debe entrar a los indicadores sin marcarse explícitamente.
 * 2. Snapshot de tarifas al registrar la cirugía (RRHH, sala, equipos y
 *    parámetros TDABC del hospital): recalcular un costo no debe cambiar
 *    la historia aunque los parámetros de Capa 1 se actualicen después.
 * 3. El hash del documento del paciente pasa de SHA-256 puro a HMAC con
 *    la APP_KEY (las cédulas son numéricas y cortas: sin clave, el hash
 *    es reversible por fuerza bruta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->string('estado', 15)->default('en_proceso')->change();
            $table->unsignedInteger('minutos_disponibles_mes_registrado')->nullable()->after('estado');
            $table->decimal('factor_indirecto_registrado', 8, 4)->nullable()->after('minutos_disponibles_mes_registrado');
            $table->decimal('costo_hora_sala_registrado', 14, 2)->nullable()->after('factor_indirecto_registrado');
        });

        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->decimal('costo_mensual_registrado', 14, 2)->nullable()->after('minutos_participacion');
        });

        Schema::table('cirugia_equipo_medico', function (Blueprint $table) {
            $table->decimal('costo_hora_registrado', 14, 2)->nullable()->after('minutos_uso');
        });

        $this->congelarTarifasExistentes();
        $this->rehashDocumentosDePacientes();
    }

    public function down(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->string('estado', 15)->default('realizada')->change();
            $table->dropColumn([
                'minutos_disponibles_mes_registrado',
                'factor_indirecto_registrado',
                'costo_hora_sala_registrado',
            ]);
        });

        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->dropColumn('costo_mensual_registrado');
        });

        Schema::table('cirugia_equipo_medico', function (Blueprint $table) {
            $table->dropColumn('costo_hora_registrado');
        });
    }

    /**
     * Las cirugías ya registradas quedan congeladas con las tarifas
     * vigentes hoy (la mejor aproximación disponible a las de su fecha).
     */
    protected function congelarTarifasExistentes(): void
    {
        $hospitales = DB::table('hospitales')->get()->keyBy('id');
        $salas = DB::table('salas_operatorias')->get()->keyBy('id');

        foreach (DB::table('cirugias')->get(['id', 'hospital_id', 'sala_operatoria_id']) as $cirugia) {
            $hospital = $hospitales->get($cirugia->hospital_id);
            $sala = $cirugia->sala_operatoria_id !== null
                ? $salas->get($cirugia->sala_operatoria_id)
                : null;

            DB::table('cirugias')->where('id', $cirugia->id)->update([
                'minutos_disponibles_mes_registrado' => $hospital !== null
                    ? (int) $hospital->horas_dia * (int) $hospital->dias_mes * 60
                    : null,
                'factor_indirecto_registrado' => $hospital?->factor_indirecto,
                'costo_hora_sala_registrado' => $sala?->costo_hora,
            ]);
        }

        $recursos = DB::table('recursos_humanos')->get()->keyBy('id');

        foreach (DB::table('miembros_equipo_quirurgico')->get(['id', 'recurso_humano_id']) as $miembro) {
            $recurso = $recursos->get($miembro->recurso_humano_id);

            if ($recurso !== null) {
                DB::table('miembros_equipo_quirurgico')->where('id', $miembro->id)->update([
                    'costo_mensual_registrado' => (float) $recurso->salario_mensual
                        + (float) $recurso->prestaciones_mensuales
                        + (float) $recurso->costos_indirectos_mensuales,
                ]);
            }
        }

        $equipos = DB::table('equipos_medicos')->get()->keyBy('id');

        foreach (DB::table('cirugia_equipo_medico')->get(['id', 'equipo_medico_id']) as $uso) {
            $equipo = $equipos->get($uso->equipo_medico_id);

            if ($equipo !== null) {
                DB::table('cirugia_equipo_medico')->where('id', $uso->id)->update([
                    'costo_hora_registrado' => $equipo->costo_hora,
                ]);
            }
        }
    }

    /**
     * Recalcula documento_hash con el nuevo HMAC. Usa el modelo porque el
     * documento está cifrado y solo el cast puede descifrarlo.
     */
    protected function rehashDocumentosDePacientes(): void
    {
        Paciente::withoutGlobalScopes()
            ->cursor()
            ->each(function (Paciente $paciente): void {
                DB::table('pacientes')->where('id', $paciente->id)->update([
                    'documento_hash' => Paciente::hashDocumento($paciente->documento),
                ]);
            });
    }
};
