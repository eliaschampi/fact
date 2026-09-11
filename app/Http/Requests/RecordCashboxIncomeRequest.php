<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final class RecordCashboxIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note'));

        $this->merge([
            'amount' => trim((string) $this->input('amount')),
            'concept' => trim((string) $this->input('concept')),
            'note' => $note === '' ? null : $note,
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'regex:'.Money::UNSIGNED_PATTERN],
            'concept' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'request_key.required' => 'No se pudo identificar de forma segura esta operación.',
            'request_key.uuid' => 'No se pudo identificar de forma segura esta operación.',
            'amount.required' => 'Escribe el importe.',
            'amount.regex' => 'El importe debe ser positivo y tener como máximo dos decimales.',
            'concept.required' => 'Describe el origen del efectivo.',
            'concept.max' => 'El concepto no puede superar 160 caracteres.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }

    /** @return array{request_key: string, amount: string, concept: string, note: string|null} */
    public function cashboxAttributes(): array
    {
        $validated = $this->validated();

        return [
            'request_key' => $validated['request_key'],
            'amount' => (string) $validated['amount'],
            'concept' => $validated['concept'],
            'note' => $validated['note'] ?? null,
        ];
    }
}
