<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'issuer_code',
    'name',
    'description',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class, 'issuer_code', 'code');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_code', 'code');
    }

    public function isOperable(): bool
    {
        return $this->is_active;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
