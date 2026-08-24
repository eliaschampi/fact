<?php

namespace App\Actions\Purchases;

use App\Models\InventoryBalance;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceivePurchase
{
    public function handle(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            /** @var Purchase $locked */
            $locked = Purchase::query()
                ->whereKey($purchase->code)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isReceivable()) {
                throw ValidationException::withMessages([
                    'status' => $locked->status === Purchase::STATUS_RECEIVED
                        ? 'La compra ya fue recibida.'
                        : 'No puedes recibir una compra anulada.',
                ]);
            }

            $items = $locked->items()->orderBy('position')->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La compra no tiene productos.',
                ]);
            }

            $variantCodes = $items->pluck('variant_code')->unique()->sort()->values();
            $variants = ProductVariant::query()
                ->with('product')
                ->whereIn('code', $variantCodes)
                ->orderBy('code')
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            foreach ($items as $item) {
                $variant = $variants->get($item->variant_code);

                if ($variant === null || ! $variant->product->isOperable()) {
                    throw ValidationException::withMessages([
                        'items' => 'Un producto inactivo no se opera. Reactívalo para recibirlo.',
                    ]);
                }

                $onHand = InventoryBalance::onHandForVariant($variant->code);
                $variant->update([
                    'cost_price' => Money::weightedAverageCost(
                        $onHand,
                        (string) $variant->cost_price,
                        $item->quantity,
                        (string) $item->unit_cost,
                    ),
                ]);

                InventoryBalance::applyDelta($variant->code, $locked->branch_code, $item->quantity);
            }

            $locked->update([
                'status' => Purchase::STATUS_RECEIVED,
                'received_at' => LimaDate::now(),
            ]);

            return $locked->refresh()->load(['items.variant.product', 'branch']);
        });
    }
}
