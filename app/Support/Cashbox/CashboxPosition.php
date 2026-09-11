<?php

namespace App\Support\Cashbox;

use App\Models\CashboxMovement;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CashboxPosition
{
    private const string LOCK_NAMESPACE = 'necofact.cashbox.cashier';

    /** @param string|list<string> $cashierCodes */
    public static function lock(string|array $cashierCodes): void
    {
        $codes = is_array($cashierCodes) ? $cashierCodes : [$cashierCodes];
        $codes = array_values(array_unique($codes));
        sort($codes);

        foreach ($codes as $code) {
            DB::selectOne(
                'SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))',
                [self::LOCK_NAMESPACE, $code],
            );
        }
    }

    public static function balanceInCents(string $cashierCode, ?string $throughDate = null): int
    {
        $balance = CashboxMovement::query()
            ->where('cashier_user_code', $cashierCode)
            ->where('business_date', '<=', $throughDate ?? LimaDate::businessDate())
            ->where('status', CashboxMovement::STATUS_ACTIVE)
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE direction
                    WHEN 'in' THEN amount
                    ELSE -amount
                END), 0)::numeric(12,2) AS balance
                SQL)
            ->value('balance');

        return Money::toCents((string) $balance);
    }

    public static function assertCanSubtract(string $cashierCode, int $amountCents): void
    {
        if ($amountCents > self::balanceInCents($cashierCode)) {
            throw ValidationException::withMessages([
                'amount' => 'El importe supera el efectivo disponible.',
            ]);
        }
    }
}
