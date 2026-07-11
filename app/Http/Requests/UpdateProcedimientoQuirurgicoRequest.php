<?php

namespace App\Http\Requests;

use App\Support\HospitalContext;
use Illuminate\Validation\Rule;

class UpdateProcedimientoQuirurgicoRequest extends StoreProcedimientoQuirurgicoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();

        $reglas['codigo_cups'] = [
            'required',
            'regex:/^\d{6}$/',
            Rule::unique('procedimientos_quirurgicos', 'codigo_cups')
                ->where('hospital_id', HospitalContext::id())
                ->ignore($this->route('procedimiento')),
        ];

        return $reglas;
    }
}
