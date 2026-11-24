<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteAgentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'agent_email' => ['required', 'email'],
            'commission_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
