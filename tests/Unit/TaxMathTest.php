<?php

namespace Tests\Unit;

use App\Support\Fiscal\TaxMath;
use Tests\TestCase;

class TaxMathTest extends TestCase
{
    public function test_single_gravado_line_splits_igv_without_float(): void
    {
        $line = TaxMath::line('118.00', 1, TaxMath::AFFECTATION_GRAVADO);

        $this->assertSame('100.00', $line->igvBase);
        $this->assertSame('100.00', $line->saleValue);
        $this->assertSame('18.00', $line->igv);
        $this->assertSame('118.00', $line->lineTotal);
        $this->assertSame('18', $line->igvPercent);
    }

    public function test_quantity_greater_than_one_rounds_at_line_then_sums(): void
    {
        $line = TaxMath::line('59.00', 2, TaxMath::AFFECTATION_GRAVADO);
        $totals = TaxMath::document([$line]);

        $this->assertSame('100.00', $totals->taxable);
        $this->assertSame('18.00', $totals->igv);
        $this->assertSame('118.00', $totals->total);
    }

    public function test_exonerado_and_inafecto_carry_no_igv(): void
    {
        $exempt = TaxMath::line('20.00', 1, TaxMath::AFFECTATION_EXONERADO);
        $unaffect = TaxMath::line('10.00', 1, TaxMath::AFFECTATION_INAFECTO);
        $totals = TaxMath::document([$exempt, $unaffect]);

        $this->assertSame('20.00', $exempt->igvBase);
        $this->assertSame('20.00', $exempt->saleValue);
        $this->assertSame('0.00', $exempt->igv);
        $this->assertSame('10.00', $unaffect->igvBase);
        $this->assertSame('0.00', $totals->taxable);
        $this->assertSame('20.00', $totals->exempt);
        $this->assertSame('10.00', $totals->unaffect);
        $this->assertSame('0.00', $totals->igv);
        $this->assertSame('30.00', $totals->total);
    }

    public function test_mixed_document_sums_after_line_rounding(): void
    {
        $gravado = TaxMath::line('10.00', 1, TaxMath::AFFECTATION_GRAVADO);
        $exempt = TaxMath::line('5.00', 1, TaxMath::AFFECTATION_EXONERADO);
        $totals = TaxMath::document([$gravado, $exempt]);

        $this->assertSame('8.47', $totals->taxable);
        $this->assertSame('1.53', $totals->igv);
        $this->assertSame('5.00', $totals->exempt);
        $this->assertSame('15.00', $totals->total);
    }
}
