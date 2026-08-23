<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_code',
    'issuer_code',
    'sku',
    'name',
    'unit_code',
    'tax_affectation',
    'price',
])]
class ProductVariant extends Model
{
    public const string UNIT_NIU = 'NIU';

    public const string UNIT_ZZ = 'ZZ';

    /** @var list<string> */
    public const array V1_UNITS = [
        self::UNIT_NIU,
        self::UNIT_ZZ,
    ];

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
