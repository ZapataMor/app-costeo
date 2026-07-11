<?php

namespace App\Http\Requests;

use App\Support\HospitalContext;
use Illuminate\Validation\Rule;

class UpdateInsumoRequest extends StoreInsumoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();

        $reglas['codigo'] = [
            'required',
            'string',
            'max:30',
            Rule::unique('insumos', 'codigo')
                ->where('hospital_id', HospitalContext::id())
                ->ignore($this->route('insumo')),
        ];

        return $reglas;
    }
}
