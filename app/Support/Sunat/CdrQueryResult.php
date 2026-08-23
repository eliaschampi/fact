<?php

namespace App\Support\Sunat;

final readonly class CdrQueryResult
{
    public function __construct(
        public bool $found,
        public ?string $cdrZip,
        public ?string $cdrCode,
        public ?string $cdrDescription,
        /** @var list<string> */
        public array $notes,
        public ?string $faultMessage,
    ) {}
}
