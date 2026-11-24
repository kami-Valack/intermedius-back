<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityOfferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'accept' => ['required', 'boolean'],
        ];
    }
}
