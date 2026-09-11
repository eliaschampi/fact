<?php

namespace App\Actions\Cashbox;

use App\Models\Branch;
use App\Models\CashboxMovement;
use App\Models\CashOutflow;
use App\Models\User;
use App\Support\Cashbox\CashboxPosition;
use App\Support\Cashbox\CashboxRequest;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordCashOutflow
{
    public function handle(
        Branch $branch,
        User $actor,
        string $requestKey,
        string $amount,
        string $concept,
        ?string $note = null,
    ): CashOutflow {
        $amountCents = Money::toCents($amount);
        $concept = trim($concept);
        $businessDate = LimaDate::businessDate();
        $normalizedAmount = Money::fromCents($amountCents);
        $fingerprint = CashboxRequest::fingerprint('cashbox.outflow.create', [
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
                'concept' => 'Describe para qué salió el efectivo.',
            ]);
        }

        return DB::transaction(function () use (
            $branch,
            $actor,
            $requestKey,
            $normalizedAmount,
            $amountCents,
            $concept,
            $note,
            $businessDate,
            $fingerprint,
        ): CashOutflow {
            $replayCode = CashboxRequest::resolveReplay(
                $requestKey,
                CashboxRequest::SOURCE_OUTFLOW,
                $fingerprint,
            );

            if ($replayCode !== null) {
                return CashOutflow::query()->findOrFail($replayCode);
            }

            CashboxPosition::lock($actor->code);
            CashboxPosition::assertCanSubtract($actor->code, $amountCents);

            $outflow = CashOutflow::query()->create([
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'cashier_user_code' => $actor->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'concept' => $concept,
                'note' => $note,
                'amount' => $normalizedAmount,
                'request_key' => $requestKey,
                'request_fingerprint' => $fingerprint,
                'status' => CashOutflow::STATUS_ACTIVE,
            ]);

            $outflow->movements()->create([
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'cashier_user_code' => $actor->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'movement_type' => CashboxMovement::TYPE_EXPENSE,
                'direction' => CashboxMovement::DIRECTION_OUT,
                'amount' => $normalizedAmount,
                'status' => CashboxMovement::STATUS_ACTIVE,
            ]);

            return $outflow->refresh()->load('movements');
        });
    }
}
