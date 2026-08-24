<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'variant_code',
    'branch_code',
    'on_hand',
])]
class InventoryBalance extends Model
{
    use HasUuids;

    protected $primaryKey = 'code';

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_code', 'code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public static function applyDelta(string $variantCode, string $branchCode, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $now = now();

        static::query()->insertOrIgnore([
            'code' => (string) Str::uuid(),
            'variant_code' => $variantCode,
            'branch_code' => $branchCode,
            'on_hand' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $query = static::query()
            ->where('variant_code', $variantCode)
            ->where('branch_code', $branchCode);

        if ($delta < 0) {
            $query->where('on_hand', '>=', abs($delta));
        }

        $updated = $query->increment('on_hand', $delta);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'items' => 'Inventario insuficiente para completar la operación.',
            ]);
        }
    }

    public static function onHandForVariant(string $variantCode): int
    {
        return (int) static::query()
            ->where('variant_code', $variantCode)
            ->sum('on_hand');
    }

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
        ];
    }
}
