<?php

namespace App\Actions\Cashbox;

use App\Models\CashboxMovement;
use App\Models\CashOutflow;
use App\Models\User;
use App\Support\Cashbox\CashboxPosition;
use App\Support\LimaDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseCashOutflow
{
    public function handle(User $actor, CashOutflow $outflow, string $reason): CashOutflow
    {
        return DB::transaction(function () use ($actor, $outflow, $reason): CashOutflow {
            /** @var CashOutflow $locked */
            $locked = CashOutflow::query()
                ->whereKey($outflow->code)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->cashier_user_code === $actor->code, 404);

            if ($locked->status !== CashOutflow::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'reason' => 'Este gasto ya fue anulado.',
                ]);
            }

            CashboxPosition::lock($actor->code);
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
                'status' => CashOutflow::STATUS_REVERSED,
            ]);

            return $locked->refresh()->load('movements');
        });
    }
}
