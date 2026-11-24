<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category' => ['required', 'in:rent,sale,commercial,all'],
            'tipologia' => ['required', 'in:T0,T1,T2,T3,T4+'],
            'municipality' => ['required', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
