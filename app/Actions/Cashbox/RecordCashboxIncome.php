<?php

namespace App\Actions\Cashbox;

use App\Models\Branch;
use App\Models\CashboxMovement;
use App\Models\User;
use App\Support\Cashbox\CashboxRequest;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordCashboxIncome
{
    public function handle(
        Branch $branch,
        User $actor,
        string $requestKey,
        string $amount,
        string $concept,
        ?string $note = null,
    ): CashboxMovement {
        $amountCents = Money::toCents($amount);
        $concept = trim($concept);
        $businessDate = LimaDate::businessDate();
        $normalizedAmount = Money::fromCents($amountCents);
        $fingerprint = CashboxRequest::fingerprint('cashbox.income.create', [
            $branch->issuer_code,
            $branch->code,
            $actor->code,
            $businessDate,
            $normalizedAmount,
            $concept,
            $note,
        ]);

        if ($amountCents <= 0 || ! Money::fitsDatabase($amountCents)) {
            throw ValidationException::withMessages([
                'amount' => 'El importe debe ser mayor que cero y respetar el límite permitido.',
            ]);
        }

        if ($concept === '') {
            throw ValidationException::withMessages([
                'concept' => 'Describe el origen del efectivo.',
            ]);
        }

        return DB::transaction(function () use (
            $branch,
            $actor,
            $requestKey,
            $normalizedAmount,
            $concept,
            $note,
            $businessDate,
            $fingerprint,
        ): CashboxMovement {
            $replayCode = CashboxRequest::resolveReplay(
                $requestKey,
                CashboxRequest::SOURCE_MANUAL_INCOME,
                $fingerprint,
            );

            if ($replayCode !== null) {
                return CashboxMovement::query()->findOrFail($replayCode);
            }

            return CashboxMovement::query()->create([
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'cashier_user_code' => $actor->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'movement_type' => CashboxMovement::TYPE_MANUAL_INCOME,
                'direction' => CashboxMovement::DIRECTION_IN,
                'amount' => $normalizedAmount,
                'concept' => $concept,
                'note' => $note,
                'request_key' => $requestKey,
                'request_fingerprint' => $fingerprint,
                'status' => CashboxMovement::STATUS_ACTIVE,
            ]);
        });
    }
}
