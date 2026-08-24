<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelPurchaseRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        if (! is_string($note) || trim($note) === '') {
            return null;
        }

        return trim($note);
    }
}
