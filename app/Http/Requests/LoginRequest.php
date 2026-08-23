<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Ingresa tu usuario.',
            'login.string' => 'El usuario debe ser texto.',
            'login.max' => 'El usuario no puede superar los 100 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
        ];
    }
}
