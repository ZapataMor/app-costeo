<?php

namespace App\Http\Requests;

class UpdateEquipoMedicoRequest extends StoreEquipoMedicoRequest
{
    // Mismas reglas del alta: equipos_medicos no tiene unique por hospital.
}
