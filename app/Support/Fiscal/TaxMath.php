<?php

namespace App\Support\Fiscal;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class TaxMath
{
    public const string AFFECTATION_GRAVADO = '10';

    public const string AFFECTATION_EXONERADO = '20';

    public const string AFFECTATION_INAFECTO = '30';

    /** @var list<string> */
    public const array V1_AFFECTATIONS = [
        self::AFFECTATION_GRAVADO,
        self::AFFECTATION_EXONERADO,
        self::AFFECTATION_INAFECTO,
    ];

    public static function igvPercent(): string
    {
        return (string) config('sunat.igv_percent', '18');
    }

    public static function line(string $unitPriceInclusive, int $quantity, string $affectation): LineComputation
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser un entero positivo.');
        }

        if (! in_array($affectation, self::V1_AFFECTATIONS, true)) {
            throw new InvalidArgumentException('Afectación IGV no soportada en v1.');
        }

        $price = self::money($unitPriceInclusive);
        $qty = BigDecimal::of($quantity);
        $lineGross = $price->multipliedBy($qty)->toScale(2, RoundingMode::HalfUp);

        if ($affectation === self::AFFECTATION_GRAVADO) {
            $percent = self::igvPercent();
            $divisor = BigDecimal::one()->plus(
                BigDecimal::of($percent)->dividedByExact(100),
            );
            $base = $lineGross->dividedBy($divisor, 2, RoundingMode::HalfUp);
            $igv = $lineGross->minus($base);
            $unitValue = $price->dividedBy($divisor, 6, RoundingMode::HalfUp);

            return new LineComputation(
                affectation: $affectation,
                quantity: $quantity,
                unitPriceInclusive: self::scale2($price),
                unitValue: $unitValue->__toString(),
                unitPrice: self::scale2($price),
                taxableBase: self::scale2($base),
                igvPercent: $percent,
                igv: self::scale2($igv),
                saleValue: self::scale2($base),
                lineTotal: self::scale2($lineGross),
            );
        }

        return new LineComputation(
            affectation: $affectation,
            quantity: $quantity,
            unitPriceInclusive: self::scale2($price),
            unitValue: self::scale2($price),
            unitPrice: self::scale2($price),
            taxableBase: '0.00',
            igvPercent: '0',
            igv: '0.00',
            saleValue: self::scale2($lineGross),
            lineTotal: self::scale2($lineGross),
        );
    }

    /**
     * @param  list<LineComputation>  $lines
     */
    public static function document(array $lines): DocumentTotals
    {
        $taxable = BigDecimal::zero();
        $exempt = BigDecimal::zero();
        $unaffect = BigDecimal::zero();
        $igv = BigDecimal::zero();
        $saleValue = BigDecimal::zero();
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            $saleValue = $saleValue->plus($line->saleValue);
            $igv = $igv->plus($line->igv);
            $total = $total->plus($line->lineTotal);

            match ($line->affectation) {
                self::AFFECTATION_GRAVADO => $taxable = $taxable->plus($line->taxableBase),
                self::AFFECTATION_EXONERADO => $exempt = $exempt->plus($line->saleValue),
                self::AFFECTATION_INAFECTO => $unaffect = $unaffect->plus($line->saleValue),
                default => throw new InvalidArgumentException('Afectación IGV no soportada en v1.'),
            };
        }

        return new DocumentTotals(
            taxable: self::scale2($taxable),
            exempt: self::scale2($exempt),
            unaffect: self::scale2($unaffect),
            igv: self::scale2($igv),
            saleValue: self::scale2($saleValue),
            total: self::scale2($total),
        );
    }

    private static function money(string $amount): BigDecimal
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Precio inválido.');
        }

        return BigDecimal::of($amount)->toScale(2, RoundingMode::Unnecessary);
    }

    private static function scale2(BigDecimal $value): string
    {
        return $value->toScale(2, RoundingMode::HalfUp)->__toString();
    }
}
