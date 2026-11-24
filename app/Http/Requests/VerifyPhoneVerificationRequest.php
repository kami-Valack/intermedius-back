<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneVerificationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'verification_id' => ['required', 'uuid', 'exists:phone_verifications,id'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
