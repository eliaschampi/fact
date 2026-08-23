<?php

namespace Database\Seeders;

use App\Models\Issuer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $issuer = Issuer::query()->first();

        if ($issuer === null) {
            return;
        }

        foreach ($this->demoCatalog() as $demo) {
            $product = Product::query()->firstOrCreate(
                [
                    'issuer_code' => $issuer->code,
                    'name' => $demo['name'],
                ],
                [
                    'description' => $demo['description'],
                    'is_active' => true,
                ],
            );

            if (! $product->wasRecentlyCreated) {
                continue;
            }

            $product->variants()->createMany(
                array_map(
                    fn (array $variant): array => [...$variant, 'issuer_code' => $issuer->code],
                    $demo['variants'],
                ),
            );
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     description: string|null,
     *     variants: list<array{sku: string, name: string|null, unit_code: string, tax_affectation: string, price: string}>
     * }>
     */
    private function demoCatalog(): array
    {
        return [
            [
                'name' => 'Café molido',
                'description' => 'Café tostado y molido de origen.',
                'variants' => [
                    [
                        'sku' => 'cafe-molido-250g',
                        'name' => 'Bolsa 250 g',
                        'unit_code' => 'NIU',
                        'tax_affectation' => '10',
                        'price' => '18.00',
                    ],
                    [
                        'sku' => 'cafe-molido-1kg',
                        'name' => 'Bolsa 1 kg',
                        'unit_code' => 'NIU',
                        'tax_affectation' => '10',
                        'price' => '60.00',
                    ],
                ],
            ],
            [
                'name' => 'Taller de barismo',
                'description' => 'Sesión práctica de barismo de 3 horas. Educación exonerada de IGV.',
                'variants' => [
                    [
                        'sku' => 'taller-barismo-sesion',
                        'name' => 'Sesión individual',
                        'unit_code' => 'ZZ',
                        'tax_affectation' => '20',
                        'price' => '120.00',
                    ],
                ],
            ],
            [
                'name' => 'Funda ecológica por canje de puntos',
                'description' => 'Entrega por canje de puntos del programa del cliente. Inafecta.',
                'variants' => [
                    [
                        'sku' => 'funda-ecologica-canje',
                        'name' => 'Tela reutilizable',
                        'unit_code' => 'NIU',
                        'tax_affectation' => '30',
                        'price' => '5.00',
                    ],
                ],
            ],
        ];
    }
}
