<?php

namespace App\Actions\Purchases;

use App\Models\InventoryBalance;
use App\Models\Purchase;
use App\Support\LimaDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelPurchase
{
    public function handle(Purchase $purchase, ?string $note = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $note): Purchase {
            /** @var Purchase $locked */
            $locked = Purchase::query()
                ->whereKey($purchase->code)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCancellable()) {
                throw ValidationException::withMessages([
                    'status' => 'La compra ya fue anulada.',
                ]);
            }

            if ($locked->status === Purchase::STATUS_RECEIVED) {
                $items = $locked->items()->orderBy('variant_code')->get();

                foreach ($items as $item) {
                    InventoryBalance::applyDelta(
                        $item->variant_code,
                        $locked->branch_code,
                        -$item->quantity,
                    );
                }
            }

            $locked->update([
                'status' => Purchase::STATUS_CANCELLED,
                'cancelled_at' => LimaDate::now(),
                'note' => $note ?? $locked->note,
            ]);

            return $locked->refresh()->load(['items.variant.product', 'branch']);
        });
    }
}
