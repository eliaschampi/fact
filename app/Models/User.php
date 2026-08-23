<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'preferred_branch_code',
    'role_code',
    'is_active',
    'is_super_admin',
])]
class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_code', 'code');
    }

    public function authAccount(): HasOne
    {
        return $this->hasOne(AuthAccount::class, 'user_code', 'code');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'user_branches',
            'user_code',
            'branch_code',
            'code',
            'code',
        );
    }

    /** Direct permission grants (presence = allowed). */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permissions',
            'user_code',
            'permission_code',
            'code',
            'code',
        );
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }
}
