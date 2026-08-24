<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_code',
    'variant_code',
    'quantity',
    'unit_cost',
    'position',
])]
class PurchaseItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'code';

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_code', 'code');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'position' => 'integer',
        ];
    }
}
