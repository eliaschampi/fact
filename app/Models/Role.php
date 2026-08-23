<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    /**
     * Permissions that may be assigned to users with this role.
     * Not automatic grants.
     */
    public function permissionScopes(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission_scopes',
            'role_code',
            'permission_code',
            'code',
            'code',
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
