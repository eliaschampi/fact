<?php

namespace App\Support\Cashbox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

final class CashboxRequest
{
    public const string SOURCE_MANUAL_INCOME = 'manual_income';

    public const string SOURCE_OUTFLOW = 'outflow';

    public const string SOURCE_OUTFLOW_RETURN = 'outflow_return';

    public const string SOURCE_TRANSFER = 'transfer';

    /** @param list<mixed> $parts */
    public static function fingerprint(string $operation, array $parts): string
    {
        try {
            $payload = json_encode(
                [$operation, ...$parts],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'request_key' => 'No se pudo identificar de forma segura esta operación.',
            ]);
        }

        return hash('sha256', $payload);
    }

    public static function resolveReplay(
        string $requestKey,
        string $expectedSource,
        string $expectedFingerprint,
    ): ?string {
        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages([
                'request_key' => 'No se pudo identificar de forma segura esta operación.',
            ]);
        }

        DB::selectOne(
            'SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))',
            ['necofact.cashbox.request', $requestKey],
        );

        $rows = DB::select(<<<'SQL'
            SELECT 'manual_income'::text AS source, code, request_fingerprint
            FROM cashbox_movements
            WHERE request_key = ?
            UNION ALL
            SELECT 'outflow', code, request_fingerprint
            FROM cash_outflows
            WHERE request_key = ?
            UNION ALL
            SELECT 'outflow_return', code, request_fingerprint
            FROM cash_outflow_returns
            WHERE request_key = ?
            UNION ALL
            SELECT 'transfer', code, request_fingerprint
            FROM cashbox_transfers
            WHERE request_key = ?
            LIMIT 2
            SQL, array_fill(0, 4, $requestKey));

        if ($rows === []) {
            return null;
        }

        $replay = $rows[0];

        if (
            count($rows) !== 1
            || $replay->source !== $expectedSource
            || $replay->request_fingerprint !== $expectedFingerprint
        ) {
            throw ValidationException::withMessages([
                'request_key' => 'La clave de operación ya fue utilizada con otra solicitud.',
            ]);
        }

        return $replay->code;
    }
}
