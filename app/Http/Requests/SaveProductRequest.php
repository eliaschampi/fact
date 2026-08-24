<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use App\Support\Fiscal\TaxMath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.code' => ['nullable', 'uuid'],
            'variants.*.sku' => [
                'required', 'string', 'min:2', 'max:40',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
            ],
            'variants.*.name' => ['nullable', 'string', 'max:80'],
            'variants.*.unit_code' => [
                'required', 'string', Rule::in(ProductVariant::V1_UNITS),
            ],
            'variants.*.tax_affectation' => [
                'required', 'string', Rule::in(TaxMath::V1_AFFECTATIONS),
            ],
            'variants.*.price' => [
                'required', 'decimal:0,2', 'min:0', 'max:9999999999.99',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del producto.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar 120 caracteres.',
            'is_active.required' => 'Indica si el producto está activo.',
            'is_active.boolean' => 'El estado del producto no es válido.',
            'variants.required' => 'Registra al menos una variante.',
            'variants.min' => 'Registra al menos una variante.',
            'variants.*.sku.required' => 'Escribe el SKU de la variante.',
            'variants.*.sku.min' => 'El SKU debe tener al menos 2 caracteres.',
            'variants.*.sku.max' => 'El SKU no puede superar 40 caracteres.',
            'variants.*.sku.regex' => 'El SKU usa sólo minúsculas, números y guiones.',
            'variants.*.name.max' => 'La presentación no puede superar 80 caracteres.',
            'variants.*.unit_code.*' => 'Selecciona una unidad válida (Cat. 03).',
            'variants.*.tax_affectation.*' => 'Selecciona una afectación IGV válida (Cat. 07).',
            'variants.*.price.required' => 'Escribe el precio de la variante.',
            'variants.*.price.decimal' => 'El precio debe ser un número decimal.',
            'variants.*.price.min' => 'El precio no puede ser negativo.',
            'variants.*.price.max' => 'El precio supera el límite del sistema.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')),
        ]);

        if (is_array($this->input('variants'))) {
            $this->merge([
                'variants' => array_map(
                    static fn (array $variant): array => [
                        ...$variant,
                        'sku' => is_string($variant['sku'] ?? null)
                            ? mb_strtolower(trim($variant['sku']))
                            : ($variant['sku'] ?? null),
                        'name' => is_string($variant['name'] ?? null)
                            ? trim($variant['name'])
                            : ($variant['name'] ?? null),
                    ],
                    $this->input('variants'),
                ),
            ]);
        }
    }

    /**
     * Validated payload shaped for SaveProduct.
     *
     * @return array{
     *     name: string,
     *     description: string|null,
     *     is_active: bool,
     *     variants: list<array{code?: string|null, sku: string, name?: string|null, unit_code: string, tax_affectation: string, price: string}>
     * }
     */
    public function productAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'variants' => array_map(
                static fn (array $variant): array => [
                    'code' => $variant['code'] ?? null,
                    'sku' => $variant['sku'],
                    'name' => $variant['name'] ?? null,
                    'unit_code' => $variant['unit_code'],
                    'tax_affectation' => $variant['tax_affectation'],
                    'price' => (string) $variant['price'],
                ],
                $validated['variants'],
            ),
        ];
    }
}
