<?php

namespace App\Actions\Cashbox;

use App\Models\CashboxMovement;
use App\Models\CashboxTransfer;
use App\Models\User;
use App\Support\Cashbox\CashboxPosition;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseCashboxTransfer
{
    public function handle(User $actor, CashboxTransfer $transfer, string $reason): CashboxTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $reason): CashboxTransfer {
            /** @var CashboxTransfer $locked */
            $locked = CashboxTransfer::query()
                ->whereKey($transfer->code)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->source_cashier_user_code === $actor->code, 404);

            if ($locked->status !== CashboxTransfer::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta transferencia ya fue anulada.',
                ]);
            }

            CashboxPosition::lock([
                $locked->source_cashier_user_code,
                $locked->destination_cashier_user_code,
            ]);
            CashboxPosition::assertCanSubtract(
                $locked->destination_cashier_user_code,
                Money::toCents((string) $locked->amount),
            );

            $reversedAt = LimaDate::now();
            $reversal = [
                'status' => CashboxMovement::STATUS_REVERSED,
                'reversed_at' => $reversedAt,
                'reversed_by_user_code' => $actor->code,
                'reversal_reason' => $reason,
            ];

            $locked->movements()
                ->where('status', CashboxMovement::STATUS_ACTIVE)
                ->update($reversal);
            $locked->update([
                ...$reversal,
                'status' => CashboxTransfer::STATUS_REVERSED,
            ]);

            return $locked->refresh()->load('movements');
        });
    }
}
