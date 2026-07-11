<?php

namespace App\Http\Requests;

use App\Support\HospitalContext;
use Illuminate\Validation\Rule;

class UpdateSalaOperatoriaRequest extends StoreSalaOperatoriaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();

        $reglas['nombre'] = [
            'required',
            'string',
            'max:255',
            Rule::unique('salas_operatorias', 'nombre')
                ->where('hospital_id', HospitalContext::id())
                ->ignore($this->route('salaOperatoria')),
        ];

        return $reglas;
    }
}
