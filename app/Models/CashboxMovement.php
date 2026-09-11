<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'issuer_code',
    'branch_code',
    'cashier_user_code',
    'registered_by_user_code',
    'business_date',
    'movement_type',
    'direction',
    'amount',
    'concept',
    'note',
    'cash_outflow_code',
    'cashbox_transfer_code',
    'request_key',
    'request_fingerprint',
    'status',
    'reversed_at',
    'reversed_by_user_code',
    'reversal_reason',
])]
class CashboxMovement extends Model
{
    public const string TYPE_MANUAL_INCOME = 'manual_income';

    public const string TYPE_EXPENSE = 'expense';

    public const string TYPE_OUTFLOW_RETURN = 'outflow_return';

    public const string TYPE_TRANSFER = 'transfer';

    public const string DIRECTION_IN = 'in';

    public const string DIRECTION_OUT = 'out';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_REVERSED = 'reversed';

    use HasUuids;

    protected $primaryKey = 'code';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_code', 'code');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_code', 'code');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_code', 'code');
    }

    public function outflow(): BelongsTo
    {
        return $this->belongsTo(CashOutflow::class, 'cash_outflow_code', 'code');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(CashboxTransfer::class, 'cashbox_transfer_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
