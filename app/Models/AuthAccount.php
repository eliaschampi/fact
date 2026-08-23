<?php

namespace App\Models;

use Database\Factories\AuthAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'login',
    'password',
    'user_code',
    'is_active',
    'last_login_at',
])]
#[Hidden(['password'])]
class AuthAccount extends Authenticatable
{
    /** @use HasFactory<AuthAccountFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
