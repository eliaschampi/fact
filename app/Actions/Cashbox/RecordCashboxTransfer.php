<?php

namespace App\Actions\Cashbox;

use App\Models\Branch;
use App\Models\CashboxMovement;
use App\Models\CashboxTransfer;
use App\Models\User;
use App\Support\Authorization\PermissionResolver;
use App\Support\Cashbox\CashboxPosition;
use App\Support\Cashbox\CashboxRequest;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordCashboxTransfer
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function handle(
        Branch $branch,
        User $actor,
        string $destinationCashierCode,
        string $requestKey,
        string $amount,
        ?string $note = null,
    ): CashboxTransfer {
        $amountCents = Money::toCents($amount);
        $businessDate = LimaDate::businessDate();
        $normalizedAmount = Money::fromCents($amountCents);
        $fingerprint = CashboxRequest::fingerprint('cashbox.transfer.create', [
            $branch->issuer_code,
            $branch->code,
            $actor->code,
            $destinationCashierCode,
            $businessDate,
            $normalizedAmount,
            $note,
        ]);

        if ($amountCents <= 0 || ! Money::fitsDatabase($amountCents)) {
            throw ValidationException::withMessages([
                'amount' => 'El importe debe ser mayor que cero y respetar el límite permitido.',
            ]);
        }

        if ($destinationCashierCode === $actor->code) {
            throw ValidationException::withMessages([
                'destination_cashier_code' => 'Elige otro cajero para transferir el efectivo.',
            ]);
        }

        return DB::transaction(function () use (
            $branch,
            $actor,
            $destinationCashierCode,
            $requestKey,
            $amountCents,
            $normalizedAmount,
            $note,
            $businessDate,
            $fingerprint,
        ): CashboxTransfer {
            $replayCode = CashboxRequest::resolveReplay(
                $requestKey,
                CashboxRequest::SOURCE_TRANSFER,
                $fingerprint,
            );

            if ($replayCode !== null) {
                return CashboxTransfer::query()->findOrFail($replayCode);
            }

            $destination = $this->permissions
                ->usersWithEffectivePermission('cashbox.manage')
                ->whereKey($destinationCashierCode)
                ->whereHas(
                    'branches',
                    fn ($query) => $query->where('branches.issuer_code', $branch->issuer_code),
                )
                ->lockForUpdate()
                ->first();

            if ($destination === null) {
                throw ValidationException::withMessages([
                    'destination_cashier_code' => 'El cajero destino no está habilitado para recibir efectivo.',
                ]);
            }

            CashboxPosition::lock([$actor->code, $destination->code]);
            CashboxPosition::assertCanSubtract($actor->code, $amountCents);

            $transfer = CashboxTransfer::query()->create([
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'source_cashier_user_code' => $actor->code,
                'destination_cashier_user_code' => $destination->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'amount' => $normalizedAmount,
                'note' => $note,
                'request_key' => $requestKey,
                'request_fingerprint' => $fingerprint,
                'status' => CashboxTransfer::STATUS_ACTIVE,
            ]);

            $movement = [
                'issuer_code' => $branch->issuer_code,
                'branch_code' => $branch->code,
                'registered_by_user_code' => $actor->code,
                'business_date' => $businessDate,
                'movement_type' => CashboxMovement::TYPE_TRANSFER,
                'amount' => $normalizedAmount,
                'status' => CashboxMovement::STATUS_ACTIVE,
            ];

            $transfer->movements()->createMany([
                [
                    ...$movement,
                    'cashier_user_code' => $actor->code,
                    'direction' => CashboxMovement::DIRECTION_OUT,
                ],
                [
                    ...$movement,
                    'cashier_user_code' => $destination->code,
                    'direction' => CashboxMovement::DIRECTION_IN,
                ],
            ]);

            return $transfer->refresh()->load('movements');
        });
    }
}
