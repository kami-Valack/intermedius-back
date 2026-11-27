<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveBankDetailsRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $iban = $this->input('iban');
        $expressNumber = $this->input('express_number');

        $this->merge([
            'iban' => is_string($iban) ? strtoupper(preg_replace('/\s+/', '', $iban)) : $iban,
            'express_number' => is_string($expressNumber) ? trim($expressNumber) : $expressNumber,
        ]);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'iban' => ['required', 'string', 'max:34', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/'],
            'express_number' => ['required', 'string', 'max:32'],
        ];
    }
}
