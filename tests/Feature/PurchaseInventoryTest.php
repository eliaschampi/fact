<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\InventoryBalance;
use App\Models\Issuer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Tests\TestCase;

class PurchaseInventoryTest extends TestCase
{
    public function test_index_requires_purchases_view(): void
    {
        $account = $this->createStaffAccount();

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->get('/purchases')
            ->assertForbidden();
    }

    public function test_store_requires_a_working_branch(): void
    {
        $account = $this->createStaffAccount(branchCount: 2);
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);

        $this->actingAs($account)
            ->post('/purchases', $this->purchasePayload($variant))
            ->assertSessionHasErrors('branch');
    }

    public function test_store_creates_in_transit_purchase_without_touching_stock(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant, quantity: 4, unitCost: '5.00'))
            ->assertRedirect();

        $purchase = Purchase::query()->sole();

        $this->assertSame(Purchase::STATUS_IN_TRANSIT, $purchase->status);
        $this->assertSame('PUR-000001', $purchase->purchase_number);
        $this->assertSame(0, InventoryBalance::query()->sum('on_hand'));
        $this->assertSame('0.00', $variant->fresh()->cost_price);
    }

    public function test_receive_enters_stock_and_sets_weighted_average_cost(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage', 'inventory.view']);
        $variant = $this->variantFor($account);
        $branch = $account->user->branches->sole();

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant, quantity: 10, unitCost: '5.00'));
        $firstCode = Purchase::query()->orderBy('purchase_number')->sole()->code;

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$firstCode}/receive")
            ->assertRedirect(route('purchases.show', $firstCode));

        $this->assertSame(10, (int) InventoryBalance::query()
            ->where('variant_code', $variant->code)
            ->where('branch_code', $branch->code)
            ->value('on_hand'));
        $this->assertSame('5.00', $variant->fresh()->cost_price);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant, quantity: 10, unitCost: '15.00'));

        $second = Purchase::query()->where('status', Purchase::STATUS_IN_TRANSIT)->sole();

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$second->code}/receive")
            ->assertRedirect();

        $this->assertSame(20, (int) InventoryBalance::query()
            ->where('variant_code', $variant->code)
            ->where('branch_code', $branch->code)
            ->value('on_hand'));
        $this->assertSame('10.00', $variant->fresh()->cost_price);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->get('/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Index')
                ->where('balances.data.0.on_hand', 20)
                ->where('balances.data.0.cost_price', '10.00'));
    }

    public function test_receive_twice_is_rejected(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant));

        $code = Purchase::query()->sole()->code;

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$code}/receive")
            ->assertRedirect();

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$code}/receive")
            ->assertSessionHasErrors('status');
    }

    public function test_cancel_in_transit_does_not_move_stock(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant, quantity: 3));

        $code = Purchase::query()->sole()->code;

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$code}/cancel")
            ->assertRedirect();

        $this->assertSame(Purchase::STATUS_CANCELLED, Purchase::query()->sole()->status);
        $this->assertSame(0, (int) InventoryBalance::query()->sum('on_hand'));
    }

    public function test_cancel_received_reverses_stock(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);
        $branch = $account->user->branches->sole();

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant, quantity: 8, unitCost: '4.00'));

        $code = Purchase::query()->sole()->code;

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$code}/receive");

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post("/purchases/{$code}/cancel")
            ->assertRedirect();

        $this->assertSame(0, (int) InventoryBalance::query()
            ->where('variant_code', $variant->code)
            ->where('branch_code', $branch->code)
            ->value('on_hand'));
    }

    public function test_inactive_product_cannot_be_purchased(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account, active: false);

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $this->purchasePayload($variant))
            ->assertSessionHasErrors('items');
    }

    public function test_store_rejects_duplicate_variant_in_payload(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['purchases.manage']);
        $variant = $this->variantFor($account);

        $payload = $this->purchasePayload($variant);
        $payload['items'][] = $payload['items'][0];

        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/purchases', $payload)
            ->assertSessionHasErrors('items.1.variant_code');
    }

    private function variantFor(AuthAccount $account, bool $active = true): ProductVariant
    {
        $issuer = Issuer::query()->findOrFail($account->user->branches->first()->issuer_code);
        $product = Product::factory()->forIssuer($issuer)->create(['is_active' => $active]);

        return ProductVariant::factory()->forProduct($product)->create();
    }

    /**
     * @return array{note: null, items: list<array{variant_code: string, quantity: int, unit_cost: string}>}
     */
    private function purchasePayload(
        ProductVariant $variant,
        int $quantity = 2,
        string $unitCost = '8.50',
    ): array {
        return [
            'note' => null,
            'items' => [
                [
                    'variant_code' => $variant->code,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ],
            ],
        ];
    }
}
