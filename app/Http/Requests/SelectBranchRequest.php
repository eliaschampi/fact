<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SelectBranchRequest extends FormRequest
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
            'branch_code' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_code.required' => 'Selecciona una sede.',
            'branch_code.uuid' => 'La sede seleccionada no es válida.',
        ];
    }
}
