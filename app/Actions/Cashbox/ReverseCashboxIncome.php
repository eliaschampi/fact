<?php

namespace App\Actions\Cashbox;

use App\Models\CashboxMovement;
use App\Models\User;
use App\Support\Cashbox\CashboxPosition;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseCashboxIncome
{
    public function handle(User $actor, CashboxMovement $movement, string $reason): CashboxMovement
    {
        return DB::transaction(function () use ($actor, $movement, $reason): CashboxMovement {
            /** @var CashboxMovement $locked */
            $locked = CashboxMovement::query()
                ->whereKey($movement->code)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $locked->cashier_user_code === $actor->code
                    && $locked->movement_type === CashboxMovement::TYPE_MANUAL_INCOME,
                404,
            );

            if ($locked->status !== CashboxMovement::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'reason' => 'Este ingreso ya fue anulado.',
                ]);
            }

            CashboxPosition::lock($actor->code);
            CashboxPosition::assertCanSubtract(
                $actor->code,
                Money::toCents((string) $locked->amount),
            );

            $locked->update([
                'status' => CashboxMovement::STATUS_REVERSED,
                'reversed_at' => LimaDate::now(),
                'reversed_by_user_code' => $actor->code,
                'reversal_reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }
}
