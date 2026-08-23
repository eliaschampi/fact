<?php

namespace App\Support\Sunat;

final readonly class TicketResult
{
    public const string PENDING = 'pending';

    public const string COMPLETED = 'completed';

    public const string ERROR = 'error';

    public function __construct(
        public string $status,
        public ?string $sunatCode,
        public ?string $cdrZip,
        public ?string $cdrCode,
        public ?string $cdrDescription,
        /** @var list<string> */
        public array $notes,
        public ?string $faultMessage,
    ) {}
}
