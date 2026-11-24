<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'visit_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'platform_commission_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'agent_commission_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
