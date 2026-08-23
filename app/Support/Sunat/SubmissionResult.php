<?php

namespace App\Support\Sunat;

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
}
