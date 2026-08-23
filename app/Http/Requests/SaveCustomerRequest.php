<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\RucNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    public function rules(): array
    {
        return [
            'doc_type' => ['required', 'string', Rule::in(Customer::V1_DOC_TYPES)],
            'doc_number' => $this->docNumberRules(),
            'name' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }

    /**
     * @return list<string|Closure>
     */
    private function docNumberRules(): array
    {
        $docType = (string) $this->input('doc_type');

        return match ($docType) {
            Customer::DOC_RUC => [
                'required',
                'regex:/^\d{11}$/',
                fn (string $attribute, mixed $value, Closure $fail): bool => RucNumber::isValid((string) $value)
                    || $fail('El RUC no pasa el dígito verificador (módulo 11).'),
            ],
            Customer::DOC_DNI => [
                'required',
                'regex:/^\d{8}$/',
                Rule::notIn(['00000000']),
            ],
            default => [
                'required',
                'regex:/^[0-9A-Z]{1,15}$/',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_type.required' => 'Selecciona el tipo de documento.',
            'doc_type.in' => 'Selecciona un tipo de documento válido (Cat. 06).',
            'doc_number.required' => 'Escribe el número de documento.',
            'doc_number.regex' => 'El número de documento no tiene el formato esperado.',
            'doc_number.not_in' => 'El DNI 00000000 no identifica a una persona.',
            'name.required' => 'Escribe el nombre o razón social.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar 150 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'doc_type' => trim((string) $this->input('doc_type')),
            'doc_number' => mb_strtoupper(trim((string) $this->input('doc_number'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array{doc_type: string, doc_number: string, name: string}
     */
    public function customerAttributes(): array
    {
        $validated = $this->validated();

        return [
            'doc_type' => $validated['doc_type'],
            'doc_number' => $validated['doc_number'],
            'name' => $validated['name'],
        ];
    }
}
