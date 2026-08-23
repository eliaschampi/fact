<?php

namespace App\Models;

use Database\Factories\IssuerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ruc',
    'legal_name',
    'trade_name',
    'tax_regime',
    'address_line',
    'ubigeo',
    'department',
    'province',
    'district',
    'capabilities',
    'environment',
])]
class Issuer extends Model
{
    public const string REGIME_NRUS = 'NRUS';

    public const string REGIME_RER = 'RER';

    public const string REGIME_RMT = 'RMT';

    public const string REGIME_GENERAL = 'GENERAL';

    public const string ENVIRONMENT_BETA = 'beta';

    public const string ENVIRONMENT_PRODUCTION = 'production';

    /** @use HasFactory<IssuerFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'issuer_code', 'code');
    }

    public function canIssueInvoice(): bool
    {
        return (bool) ($this->capabilities['invoice'] ?? false);
    }

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
        ];
    }
}
