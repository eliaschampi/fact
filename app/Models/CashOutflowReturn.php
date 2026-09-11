<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'issuer_code',
    'branch_code',
    'cash_outflow_code',
    'created_by_user_code',
    'business_date',
    'amount',
    'note',
    'request_key',
    'request_fingerprint',
])]
class CashOutflowReturn extends Model
{
    use HasUuids;

    protected $primaryKey = 'code';

    public function outflow(): BelongsTo
    {
        return $this->belongsTo(CashOutflow::class, 'cash_outflow_code', 'code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_code', 'code');
    }

    protected function casts(): array
    {
        return ['business_date' => 'date'];
    }
}
