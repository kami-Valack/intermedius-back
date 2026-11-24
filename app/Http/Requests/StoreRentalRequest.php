<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRentalRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'tenant' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'nif' => ['required', 'string', 'max:32'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['required', 'integer', 'in:6,12,24'],
            'payment_method' => ['required', 'in:mcx_express,reference'],
        ];
    }
}
