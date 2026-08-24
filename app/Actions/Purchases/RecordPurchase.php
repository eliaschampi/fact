<?php

namespace App\Actions\Purchases;

use App\Models\Branch;
use App\Models\Issuer;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\User;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPurchase
{
    /**
     * @param  list<array{variant_code: string, quantity: int, unit_cost: string}>  $items
     */
    public function handle(Branch $branch, User $user, array $items, ?string $note = null): Purchase
    {
        $this->assertDistinctVariants($items);

        return DB::transaction(function () use ($branch, $user, $items, $note): Purchase {
            Issuer::query()->whereKey($branch->issuer_code)->lockForUpdate()->firstOrFail();

            $this->lockOperableVariants(
                $branch->issuer_code,
                array_column($items, 'variant_code'),
            );

            $purchase = Purchase::query()->create([
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'user_code' => $user->code,
                'purchase_number' => Purchase::nextNumberForIssuer($branch->issuer_code),
                'status' => Purchase::STATUS_IN_TRANSIT,
                'ordered_at' => LimaDate::businessDate(),
                'note' => $note,
            ]);

            foreach (array_values($items) as $position => $item) {
                $purchase->items()->create([
                    'variant_code' => $item['variant_code'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => Money::normalize($item['unit_cost']),
                    'position' => $position,
                ]);
            }

            return $purchase->refresh()->load(['items.variant.product', 'branch']);
        });
    }

    /**
     * @param  list<array{variant_code: string, quantity: int, unit_cost: string}>  $items
     */
    private function assertDistinctVariants(array $items): void
    {
        $codes = array_column($items, 'variant_code');

        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages([
                'items' => 'Cada variante sólo puede aparecer una vez en la compra.',
            ]);
        }
    }

    /**
     * @param  list<string>  $variantCodes
     */
    private function lockOperableVariants(string $issuerCode, array $variantCodes): void
    {
        $variants = ProductVariant::query()
            ->with('product')
            ->where('issuer_code', $issuerCode)
            ->whereIn('code', $variantCodes)
            ->orderBy('code')
            ->lockForUpdate()
            ->get()
            ->keyBy('code');

        if ($variants->count() !== count(array_unique($variantCodes))) {
            throw ValidationException::withMessages([
                'items' => 'Una variante no pertenece a este emisor o ya no existe.',
            ]);
        }

        foreach ($variants as $variant) {
            if (! $variant->product->isOperable()) {
                throw ValidationException::withMessages([
                    'items' => 'Un producto inactivo no se opera. Reactívalo para comprarlo.',
                ]);
            }
        }
    }
}
