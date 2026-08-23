<?php

namespace App\Actions\Catalog;

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveCustomer
{
    /**
     * @param  array{doc_type: string, doc_number: string, name: string}  $attributes
     */
    public function handle(Customer $customer, array $attributes): Customer
    {
        if ($customer->exists && $customer->isFinalConsumer()) {
            throw ValidationException::withMessages([
                'doc_type' => 'El consumidor final es una fila del sistema; no se edita.',
            ]);
        }

        if ($this->isFinalConsumerIdentity($attributes)) {
            throw ValidationException::withMessages([
                'doc_number' => 'El consumidor final lo crea el sistema al emitir boletas.',
            ]);
        }

        // The savepoint isolates the unique violation from any outer
        // transaction (a failed statement poisons it in PostgreSQL).
        try {
            DB::transaction(function () use ($customer, $attributes): void {
                $customer->fill([
                    'doc_type' => $attributes['doc_type'],
                    'doc_number' => $attributes['doc_number'],
                    'name' => $attributes['name'],
                ])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'doc_number' => 'Ya existe un cliente con ese documento.',
            ]);
        }

        return $customer->refresh();
    }

    /**
     * @param  array{doc_type: string, doc_number: string, name: string}  $attributes
     */
    private function isFinalConsumerIdentity(array $attributes): bool
    {
        return $attributes['doc_type'] === Customer::DOC_OTHER
            && $attributes['doc_number'] === Customer::FINAL_CONSUMER_DOC_NUMBER;
    }
}
