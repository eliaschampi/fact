<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final class RecordCashOutflowReturnRequest extends FormRequest
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
            'note' => $note === '' ? null : $note,
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'regex:'.Money::UNSIGNED_PATTERN],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'request_key.required' => 'No se pudo identificar de forma segura esta operación.',
            'request_key.uuid' => 'No se pudo identificar de forma segura esta operación.',
            'amount.required' => 'Escribe el importe del Vuelto.',
            'amount.regex' => 'El Vuelto debe ser positivo y tener como máximo dos decimales.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }

    /** @return array{request_key: string, amount: string, note: string|null} */
    public function cashboxAttributes(): array
    {
        $validated = $this->validated();

        return [
            'request_key' => $validated['request_key'],
            'amount' => (string) $validated['amount'],
            'note' => $validated['note'] ?? null,
        ];
    }
}
