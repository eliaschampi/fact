<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordPurchaseRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_code' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto.',
            'items.min' => 'Agrega al menos un producto.',
            'items.*.variant_code.required' => 'Selecciona una variante.',
            'items.*.variant_code.distinct' => 'Cada variante sólo puede aparecer una vez en la compra.',
            'items.*.quantity.required' => 'Escribe la cantidad.',
            'items.*.quantity.integer' => 'La cantidad debe ser un entero.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.unit_cost.required' => 'Escribe el costo unitario.',
            'items.*.unit_cost.decimal' => 'El costo debe tener como máximo dos decimales.',
            'items.*.unit_cost.min' => 'El costo no puede ser negativo.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }

    /**
     * @return array{note: string|null, items: list<array{variant_code: string, quantity: int, unit_cost: string}>}
     */
    public function purchaseAttributes(): array
    {
        $validated = $this->validated();

        return [
            'note' => isset($validated['note']) && is_string($validated['note']) && trim($validated['note']) !== ''
                ? trim($validated['note'])
                : null,
            'items' => array_map(
                static fn (array $item): array => [
                    'variant_code' => $item['variant_code'],
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (string) $item['unit_cost'],
                ],
                $validated['items'],
            ),
        ];
    }
}
