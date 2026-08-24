<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'issuer_code',
    'branch_code',
    'user_code',
    'purchase_number',
    'status',
    'ordered_at',
    'received_at',
    'cancelled_at',
    'note',
])]
class Purchase extends Model
{
    public const string STATUS_IN_TRANSIT = 'in_transit';

    public const string STATUS_RECEIVED = 'received';

    public const string STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const array STATUSES = [
        self::STATUS_IN_TRANSIT,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    /** @use HasFactory<PurchaseFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class, 'issuer_code', 'code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_code', 'code');
    }

    public function isReceivable(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT
            || $this->status === self::STATUS_RECEIVED;
    }

    public function totalCost(): string
    {
        $cents = 0;

        foreach ($this->items as $item) {
            $cents += $item->quantity * Money::toCents((string) $item->unit_cost);
        }

        return Money::fromCents($cents);
    }

    public static function nextNumberForIssuer(string $issuerCode): string
    {
        $last = static::query()
            ->where('issuer_code', $issuerCode)
            ->max('purchase_number');

        $next = $last === null ? 1 : ((int) substr((string) $last, 4)) + 1;

        return sprintf('PUR-%06d', $next);
    }

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'received_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
