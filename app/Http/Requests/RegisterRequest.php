<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $role = $this->input('role');
        $needsDocument = in_array($role, ['owner', 'agent'], true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'phone_verification_id' => ['required', 'uuid', 'exists:phone_verifications,id'],
            'nif' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'in:client,owner,agent'],
            'password' => ['required', 'string', 'min:6'],
            'document' => [$needsDocument ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'role.in' => 'O registo público só admite cliente, proprietário ou intermediário.',
            'document.required' => 'Anexe o BI/NIF para validar este perfil.',
        ];
    }
}
