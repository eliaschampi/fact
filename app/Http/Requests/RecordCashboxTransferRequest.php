<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final class RecordCashboxTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note'));

        $this->merge([
            'destination_cashier_code' => trim((string) $this->input('destination_cashier_code')),
            'amount' => trim((string) $this->input('amount')),
            'note' => $note === '' ? null : $note,
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'destination_cashier_code' => ['required', 'uuid'],
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
            'destination_cashier_code.required' => 'Selecciona el cajero que recibe el efectivo.',
            'destination_cashier_code.uuid' => 'El cajero seleccionado no es válido.',
            'amount.required' => 'Escribe el importe.',
            'amount.regex' => 'El importe debe ser positivo y tener como máximo dos decimales.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }

    /** @return array{request_key: string, destination_cashier_code: string, amount: string, note: string|null} */
    public function cashboxAttributes(): array
    {
        $validated = $this->validated();

        return [
            'request_key' => $validated['request_key'],
            'destination_cashier_code' => $validated['destination_cashier_code'],
            'amount' => (string) $validated['amount'],
            'note' => $validated['note'] ?? null,
        ];
    }
}
