<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Paciente;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;

/**
 * Pacientes de cada hospital.
 *
 * Los documentos son deterministas (prefijo por hospital + correlativo) en vez
 * de aleatorios, y por eso el seeder es idempotente: `documento_hash` tiene
 * índice único junto al hospital, así que reejecutarlo reconoce a los mismos
 * pacientes en lugar de duplicarlos.
 *
 * El documento va cifrado en reposo (Ley 1581/2012); el hash lo calcula el
 * evento `saving` del modelo, así que aquí no hay que tocarlo — pero sí hay
 * que evitar `WithoutModelEvents` al llamar a este seeder.
 *
 * Todos los pacientes son ficticios y están marcados con [SEMILLA].
 */
class PacienteSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * Cuántos pacientes recibe cada hospital, y con qué prefijo de documento.
     *
     * @var array<string, array{cantidad: int, prefijo: int}>
     */
    public const POR_HOSPITAL = [
        '800100200-1' => ['cantidad' => 60, 'prefijo' => 1_010_000_000],
        '800300400-2' => ['cantidad' => 25, 'prefijo' => 1_020_000_000],
    ];

    /** @var list<string> */
    protected const NOMBRES_F = [
        'María José', 'Luz Marina', 'Yorlenis', 'Deisy', 'Katherine', 'Shirley',
        'Nelcy', 'Aura', 'Yaneth', 'Dayana', 'Marielis', 'Sindy', 'Roselia',
        'Anais', 'Elvira', 'Yulieth', 'Karina', 'Zoraida',
    ];

    /** @var list<string> */
    protected const NOMBRES_M = [
        'José Gregorio', 'Wilmer', 'Alfonso', 'Deiver', 'Jhon Jairo', 'Eder',
        'Ramiro', 'Alexander', 'Uriel', 'Wilfrido', 'Yeison', 'Nairo',
        'Everardo', 'Adalberto', 'Hugo', 'Amaury',
    ];

    /** @var list<string> */
    protected const APELLIDOS = [
        'Epiayú', 'Uriana', 'Pushaina', 'Ipuana', 'Jusayú', 'Arpushana',
        'Gámez', 'Redondo', 'Brito', 'Solano', 'Mengual', 'Ojeda', 'Freyle',
        'Iguarán', 'Deluque', 'Cotes', 'Movil', 'Pana', 'Choles', 'Curvelo',
        'Barros', 'Mejía', 'Zuleta', 'Daza',
    ];

    /** @var list<string> */
    protected const ASEGURADORES = [
        'EPS Familiar', 'Nueva EPS', 'Cajacopi', 'Anas Wayuu', 'Sanitas',
        'Dusakawi', 'Coosalud',
    ];

    /** @var list<string> */
    protected const MUNICIPIOS = [
        'Riohacha', 'Maicao', 'Uribia', 'Manaure', 'Fonseca', 'Albania',
        'Dibulla', 'Barrancas',
    ];

    public function run(): void
    {
        foreach (self::POR_HOSPITAL as $nit => $config) {
            $hospital = Hospital::query()->where('nit', $nit)->first();

            if ($hospital === null) {
                $this->advertir("Sin hospital con NIT {$nit}: se omiten sus pacientes. Ejecuta HospitalSeeder primero.");

                continue;
            }

            $creados = $this->sembrar($hospital, $config['cantidad'], $config['prefijo']);
            $this->informar("Pacientes en {$hospital->nombre}: {$creados} nuevos de {$config['cantidad']}.");
        }
    }

    /**
     * Siembra `$cantidad` pacientes y devuelve cuántos eran nuevos.
     */
    public function sembrar(Hospital $hospital, int $cantidad, int $prefijoDocumento): int
    {
        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);
        $nuevos = 0;

        try {
            for ($i = 1; $i <= $cantidad; $i++) {
                $documento = (string) ($prefijoDocumento + $i);

                if ($this->existe($hospital, $documento)) {
                    continue;
                }

                Paciente::create($this->atributos($hospital, $documento, $i));
                $nuevos++;
            }
        } finally {
            HospitalContext::set($anterior);
        }

        return $nuevos;
    }

    protected function existe(Hospital $hospital, string $documento): bool
    {
        return Paciente::query()
            ->where('hospital_id', $hospital->id)
            ->where('documento_hash', Paciente::hashDocumento($documento))
            ->exists();
    }

    /**
     * Perfil demográfico derivado del correlativo, no aleatorio: dos corridas
     * del seeder describen al mismo paciente de la misma forma.
     *
     * @return array<string, mixed>
     */
    protected function atributos(Hospital $hospital, string $documento, int $i): array
    {
        $esFemenino = $i % 2 === 0;
        $nombres = $esFemenino ? self::NOMBRES_F : self::NOMBRES_M;

        // Entre 16 y 79 años, repartidos de forma estable.
        $edad = 16 + ($i * 7) % 64;

        return [
            'hospital_id' => $hospital->id,
            'tipo_documento' => 'CC',
            'documento' => $documento,
            'nombres' => $nombres[$i % count($nombres)],
            'apellidos' => sprintf(
                '%s %s [SEMILLA]',
                self::APELLIDOS[$i % count(self::APELLIDOS)],
                self::APELLIDOS[($i * 3 + 5) % count(self::APELLIDOS)],
            ),
            'fecha_nacimiento' => now()->subYears($edad)->subDays($i * 11)->toDateString(),
            'sexo' => $esFemenino ? 'F' : 'M',
            // El régimen subsidiado domina en La Guajira; 3 de cada 4.
            'regimen' => $i % 4 === 0 ? 'contributivo' : 'subsidiado',
            'asegurador' => self::ASEGURADORES[$i % count(self::ASEGURADORES)],
            'zona' => $i % 3 === 0 ? 'rural' : 'urbana',
            'municipio' => self::MUNICIPIOS[$i % count(self::MUNICIPIOS)],
        ];
    }
}
