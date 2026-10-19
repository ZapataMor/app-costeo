<?php

namespace App\Http\Requests;

use App\Models\Hospital;
use Illuminate\Validation\Rule;

class UpdateInstitucionRequest extends StoreInstitucionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Hospital $hospital */
        $hospital = $this->route('hospital');

        return [
            ...parent::rules(),
            'nit' => ['required', 'string', 'max:20', Rule::unique('hospitales', 'nit')->ignore($hospital->id)],
        ];
    }
}
