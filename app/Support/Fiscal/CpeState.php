<?php

namespace App\Support\Fiscal;

use InvalidArgumentException;

final class CpeState
{
    public const string RESERVED = 'reserved';

    public const string SIGNED = 'signed';

    public const string QUEUED = 'queued';

    public const string SUBMITTED = 'submitted';

    public const string ACCEPTED = 'accepted';

    public const string ACCEPTED_WITH_OBSERVATIONS = 'accepted_with_observations';

    public const string REJECTED = 'rejected';

    public const string TRANSPORT_ERROR = 'transport_error';

    public const string UNKNOWN = 'unknown';

    public const string PENDING_SUMMARY = 'pending_summary';

    public const string IN_SUMMARY = 'in_summary';

    public const string CORRECTED = 'corrected';

    public const string CANCELLED_BY_SUMMARY = 'cancelled_by_summary';

    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        self::RESERVED => [self::SIGNED],
        self::SIGNED => [self::QUEUED],
        self::QUEUED => [self::SUBMITTED, self::PENDING_SUMMARY],
        self::SUBMITTED => [
            self::ACCEPTED,
            self::ACCEPTED_WITH_OBSERVATIONS,
            self::REJECTED,
            self::TRANSPORT_ERROR,
            self::UNKNOWN,
        ],
        self::PENDING_SUMMARY => [self::IN_SUMMARY],
        self::IN_SUMMARY => [self::ACCEPTED, self::PENDING_SUMMARY, self::CANCELLED_BY_SUMMARY],
        self::UNKNOWN => [self::ACCEPTED, self::ACCEPTED_WITH_OBSERVATIONS, self::REJECTED],
        self::TRANSPORT_ERROR => [self::SUBMITTED],
        self::ACCEPTED => [self::CORRECTED],
        self::ACCEPTED_WITH_OBSERVATIONS => [self::CORRECTED],
    ];

    /** @var list<string> */
    public const array TERMINAL = [
        self::ACCEPTED,
        self::ACCEPTED_WITH_OBSERVATIONS,
        self::REJECTED,
        self::CORRECTED,
        self::CANCELLED_BY_SUMMARY,
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Transición fiscal no permitida: {$from} → {$to}.",
            );
        }
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function canRetrySameXml(string $status): bool
    {
        return $status === self::TRANSPORT_ERROR;
    }
}
