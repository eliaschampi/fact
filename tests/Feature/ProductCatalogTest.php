<?php

namespace Tests\Feature;

use App\Models\Issuer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    public function test_index_requires_products_view(): void
    {
        $account = $this->createStaffAccount();

        $this->actingAs($account)
            ->get('/products')
            ->assertForbidden();
    }

    public function test_index_lists_variants_with_filters(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.view']);

        $product = Product::factory()->create(['name' => 'Café molido']);
        ProductVariant::factory()->forProduct($product)->create([
            'sku' => 'cafe-molido-250g',
            'price' => '18.00',
        ]);

        $inactive = Product::factory()->create(['is_active' => false]);
        ProductVariant::factory()->forProduct($inactive)->create(['sku' => 'otro-producto']);

        $this->actingAs($account)
            ->get('/products?q=cafe')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Index')
                    ->where('variants.total', 1)
                    ->where('filters.q', 'cafe'),
            );

        $this->actingAs($account)
            ->get('/products?status=inactive')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('variants.total', 1));
    }

    public function test_store_requires_products_manage(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.view']);

        $this->actingAs($account)
            ->post('/products', $this->productPayload())
            ->assertForbidden();
    }

    public function test_store_creates_product_with_normalized_variant(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $this->actingAs($account)
            ->post('/products', $this->productPayload(sku: 'Cafe-Molido-250G', price: '18.5'))
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('name', 'Café molido')->firstOrFail();

        $this->assertSame($product->isOperable(), true);
        $this->assertDatabaseHas('product_variants', [
            'product_code' => $product->code,
            'sku' => 'cafe-molido-250g',
            'unit_code' => 'NIU',
            'tax_affectation' => '10',
            'price' => '18.50',
        ]);
    }

    public function test_store_rejects_duplicate_sku_with_friendly_error(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $issuer = Issuer::query()->sole();
        $existing = Product::factory()->forIssuer($issuer)->create();
        ProductVariant::factory()->forProduct($existing)->create(['sku' => 'cafe-molido-250g']);

        $this->actingAs($account)
            ->post('/products', $this->productPayload(sku: 'cafe-molido-250g'))
            ->assertSessionHasErrors('variants');
    }

    public function test_store_rejects_unsupported_affectation(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $payload = $this->productPayload();
        $payload['variants'][0]['tax_affectation'] = '40';

        $this->actingAs($account)
            ->post('/products', $payload)
            ->assertSessionHasErrors('variants.0.tax_affectation');
    }

    public function test_store_rejects_non_decimal_price(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $payload = $this->productPayload();
        $payload['variants'][0]['price'] = '1e5';

        $this->actingAs($account)
            ->post('/products', $payload)
            ->assertSessionHasErrors('variants.0.price');
    }

    public function test_store_rejects_price_with_more_than_two_decimals(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $payload = $this->productPayload();
        $payload['variants'][0]['price'] = '18.505';

        $this->actingAs($account)
            ->post('/products', $payload)
            ->assertSessionHasErrors('variants.0.price');
    }

    public function test_variant_cannot_belong_to_a_different_issuer_than_its_product(): void
    {
        $product = Product::factory()->create();
        $otherIssuer = Issuer::factory()->create();

        $this->expectException(QueryException::class);

        ProductVariant::factory()->forProduct($product)->create([
            'issuer_code' => $otherIssuer->code,
        ]);
    }

    public function test_update_edits_variant_and_deactivates_product(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->forProduct($product)->create([
            'sku' => 'cafe-molido-250g',
            'price' => '18.00',
        ]);

        $this->actingAs($account)
            ->put("/products/{$product->code}", [
                'name' => 'Café molido premium',
                'description' => null,
                'is_active' => false,
                'variants' => [
                    [
                        'code' => $variant->code,
                        'sku' => 'cafe-molido-250g',
                        'name' => 'Bolsa 250 g',
                        'unit_code' => 'NIU',
                        'tax_affectation' => '10',
                        'price' => '20.50',
                    ],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertSame(false, $product->fresh()->isOperable());
        $this->assertDatabaseHas('product_variants', [
            'code' => $variant->code,
            'price' => '20.50',
        ]);
    }

    public function test_inactive_product_rejects_edits_without_reactivation(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $product = Product::factory()->create(['is_active' => false]);
        ProductVariant::factory()->forProduct($product)->create();

        $payload = $this->productPayload(name: 'Otro nombre', active: false);
        $payload['variants'][0]['sku'] = 'sku-distinto-01';

        $this->actingAs($account)
            ->put("/products/{$product->code}", $payload)
            ->assertSessionHasErrors('is_active');
    }

    public function test_inactive_product_can_be_reactivated_with_edits(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['products.manage']);

        $product = Product::factory()->create(['is_active' => false]);
        $variant = ProductVariant::factory()->forProduct($product)->create();

        $payload = $this->productPayload(name: 'Café reactivado');
        $payload['variants'][0]['code'] = $variant->code;
        $payload['variants'][0]['sku'] = $variant->sku;

        $this->actingAs($account)
            ->put("/products/{$product->code}", $payload)
            ->assertRedirect(route('products.index'));

        $this->assertSame(true, $product->fresh()->isOperable());
        $this->assertDatabaseHas('products', [
            'code' => $product->code,
            'name' => 'Café reactivado',
        ]);
    }

    /**
     * @return array{name: string, description: string|null, is_active: bool, variants: list<array{code?: string, sku: string, name?: string, unit_code: string, tax_affectation: string, price: string}>}
     */
    private function productPayload(
        string $name = 'Café molido',
        ?string $sku = 'cafe-molido-250g',
        string $price = '18.50',
        bool $active = true,
    ): array {
        return [
            'name' => $name,
            'description' => 'Café tostado y molido.',
            'is_active' => $active,
            'variants' => [
                [
                    'sku' => $sku,
                    'name' => 'Bolsa 250 g',
                    'unit_code' => 'NIU',
                    'tax_affectation' => '10',
                    'price' => $price,
                ],
            ],
        ];
    }
}
