<?php

namespace App\Support\Fiscal;

final readonly class DocumentTotals
{
    public function __construct(
        public string $taxable,
        public string $exempt,
        public string $unaffect,
        public string $igv,
        public string $saleValue,
        public string $total,
    ) {}
}
