<?php

namespace App\Support\Sunat;

use Throwable;

final readonly class SubmissionResult
{
    public function __construct(
        public bool $transportOk,
        public ?string $ticket,
        public ?string $cdrZip,
        public ?string $cdrCode,
        public ?string $cdrDescription,
        /** @var list<string> */
        public array $notes,
        public ?string $faultCode,
        public ?string $faultMessage,
        public string $endpoint,
    ) {}

    public static function transportError(string $endpoint, Throwable $exception): self
    {
        return new self(
            transportOk: false,
            ticket: null,
            cdrZip: null,
            cdrCode: null,
            cdrDescription: null,
            notes: [],
            faultCode: $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            faultMessage: $exception->getMessage(),
            endpoint: $endpoint,
        );
    }

    public static function fault(
        string $endpoint,
        ?string $faultCode,
        ?string $faultMessage,
        ?string $ticket = null,
    ): self {
        return new self(
            transportOk: false,
            ticket: $ticket,
            cdrZip: null,
            cdrCode: null,
            cdrDescription: null,
            notes: [],
            faultCode: $faultCode,
            faultMessage: $faultMessage,
            endpoint: $endpoint,
        );
    }
}
