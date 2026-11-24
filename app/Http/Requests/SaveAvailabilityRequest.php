<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAvailabilityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,7'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'slot' => ['required', 'integer', 'in:30,60,90'],
        ];
    }
}
