<?php

namespace App\Http\Controllers;

use App\Services\Indicators\ResumenHospital;
use App\Support\HospitalContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portada del administrador: a donde llega al iniciar sesión.
 *
 * Antes aterrizaba en Parámetros (configuración) y lo primero que veía era
 * un catálogo de salarios; ahora ve cómo va el mes y qué le toca revisar.
 */
class InicioController extends Controller
{
    public function index(ResumenHospital $resumen): Response
    {
        // `hospital.requerido` garantiza que hay hospital activo.
        $hospitalId = (int) HospitalContext::id();

        return Inertia::render('inicio', [
            'indicadores' => $resumen->indicadores($hospitalId),
            'atencion' => $resumen->atencion($hospitalId),
        ]);
    }
}
