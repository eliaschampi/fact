<?php

namespace App\Support\Fiscal;

final readonly class LineComputation
{
    public function __construct(
        public string $affectation,
        public int $quantity,
        public string $unitPriceInclusive,
        public string $unitValue,
        public string $unitPrice,
        public string $igvBase,
        public string $igvPercent,
        public string $igv,
        public string $saleValue,
        public string $lineTotal,
    ) {}
}
