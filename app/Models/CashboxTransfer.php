<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'issuer_code',
    'branch_code',
    'source_cashier_user_code',
    'destination_cashier_user_code',
    'registered_by_user_code',
    'business_date',
    'amount',
    'note',
    'request_key',
    'request_fingerprint',
    'status',
    'reversed_at',
    'reversed_by_user_code',
    'reversal_reason',
])]
class CashboxTransfer extends Model
{
    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_REVERSED = 'reversed';

    use HasUuids;

    protected $primaryKey = 'code';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public function sourceCashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_cashier_user_code', 'code');
    }

    public function destinationCashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_cashier_user_code', 'code');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashboxMovement::class, 'cashbox_transfer_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
