<?php

namespace App\Http\Requests;

class UpdateRecursoHumanoRequest extends StoreRecursoHumanoRequest
{
    // Mismas reglas del alta: recursos_humanos no tiene unique por hospital.
}
