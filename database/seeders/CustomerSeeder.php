<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Issuer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $issuer = Issuer::query()->first();

        if ($issuer === null) {
            return;
        }

        Customer::query()->firstOrCreate(
            [
                'issuer_code' => $issuer->code,
                'doc_type' => Customer::DOC_OTHER,
                'doc_number' => Customer::FINAL_CONSUMER_DOC_NUMBER,
            ],
            ['name' => 'Consumidor final'],
        );

        $demo = [
            [
                'doc_type' => Customer::DOC_DNI,
                'doc_number' => '12345678',
                'name' => 'María Quispe Huamán',
            ],
            [
                'doc_type' => Customer::DOC_RUC,
                'doc_number' => '20512333797',
                'name' => 'COMERCIAL ANDINA SAC',
            ],
        ];

        foreach ($demo as $customer) {
            Customer::query()->firstOrCreate(
                [
                    'issuer_code' => $issuer->code,
                    'doc_type' => $customer['doc_type'],
                    'doc_number' => $customer['doc_number'],
                ],
                ['name' => $customer['name']],
            );
        }
    }
}
