<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'issuer_code',
    'name',
    'address_line',
    'ubigeo',
    'sunat_establishment_code',
    'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class, 'issuer_code', 'code');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_branches',
            'branch_code',
            'user_code',
            'code',
            'code',
        );
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
