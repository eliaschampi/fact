<?php

namespace App\Actions\Cashbox;

use App\Models\Branch;
use App\Models\CashboxMovement;
use App\Models\CashOutflow;
use App\Models\CashOutflowReturn;
use App\Models\User;
use App\Support\Cashbox\CashboxPosition;
use App\Support\Cashbox\CashboxRequest;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordCashOutflowReturn
{
    public function handle(
        Branch $branch,
        User $actor,
        CashOutflow $outflow,
        string $requestKey,
        string $amount,
        ?string $note = null,
    ): CashOutflowReturn {
        $amountCents = Money::toCents($amount);
        $businessDate = LimaDate::businessDate();
        $normalizedAmount = Money::fromCents($amountCents);
        $fingerprint = CashboxRequest::fingerprint('cashbox.outflow.return', [
            $branch->issuer_code,
            $branch->code,
            $actor->code,
            $outflow->code,
            $businessDate,
            $normalizedAmount,
            $note,
        ]);

        if ($amountCents <= 0 || ! Money::fitsDatabase($amountCents)) {
            throw ValidationException::withMessages([
                'amount' => 'El Vuelto debe ser mayor que cero y respetar el límite permitido.',
            ]);
        }

        return DB::transaction(function () use (
            $branch,
            $actor,
            $outflow,
            $requestKey,
            $amountCents,
            $normalizedAmount,
            $note,
            $businessDate,
            $fingerprint,
        ): CashOutflowReturn {
            $replayCode = CashboxRequest::resolveReplay(
                $requestKey,
                CashboxRequest::SOURCE_OUTFLOW_RETURN,
                $fingerprint,
            );

            if ($replayCode !== null) {
                return CashOutflowReturn::query()->findOrFail($replayCode);
            }

            /** @var CashOutflow $locked */
            $locked = CashOutflow::query()
                ->with('cashReturn')
                ->whereKey($outflow->code)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $locked->cashier_user_code === $actor->code
                    && $locked->issuer_code === $branch->issuer_code,
                404,
            );

            if ($locked->status !== CashOutflow::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'amount' => 'Sólo un gasto vigente puede registrar Vuelto.',
                ]);
            }

            if ($locked->cashReturn !== null) {
                throw ValidationException::withMessages([
                    'amount' => 'Este gasto ya tiene un Vuelto registrado.',
                ]);
            }

            if ($amountCents > Money::toCents((string) $locked->amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'El Vuelto no puede superar el importe del gasto.',
                ]);
            }

            CashboxPosition::lock($actor->code);

            $return = CashOutflowReturn::query()->create([
                'issuer_code' => $locked->issuer_code,
                'branch_code' => $branch->code,
                'cash_outflow_code' => $locked->code,
                'created_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'amount' => $normalizedAmount,
                'note' => $note,
                'request_key' => $requestKey,
                'request_fingerprint' => $fingerprint,
            ]);

            $locked->movements()->create([
                'issuer_code' => $locked->issuer_code,
                'branch_code' => $branch->code,
                'cashier_user_code' => $actor->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'movement_type' => CashboxMovement::TYPE_OUTFLOW_RETURN,
                'direction' => CashboxMovement::DIRECTION_IN,
                'amount' => $normalizedAmount,
                'status' => CashboxMovement::STATUS_ACTIVE,
            ]);

            return $return;
        });
    }
}
