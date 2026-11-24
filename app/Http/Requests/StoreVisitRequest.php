<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'payment_method' => ['required', 'in:mcx_express,reference'],
        ];
    }
}
