<?php

namespace App\Support\Authorization;

use App\Models\AuthAccount;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

final class PermissionResolver
{
    private const string REQUEST_CACHE_KEY = self::class.'.effective_names';

    public function __construct(private readonly Request $request) {}

    public function can(AuthAccount $account, string $permissionName): bool
    {
        return in_array($permissionName, $this->effectiveNames($account), true);
    }

    /**
     * @return list<string>
     */
    public function effectiveNames(AuthAccount $account): array
    {
        $cacheKey = self::REQUEST_CACHE_KEY.'.'.$account->getKey();

        if ($this->request->attributes->has($cacheKey)) {
            /** @var list<string> */
            return $this->request->attributes->get($cacheKey);
        }

        $names = $this->resolveEffectiveNames($account);
        $this->request->attributes->set($cacheKey, $names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function resolveEffectiveNames(AuthAccount $account): array
    {
        $user = $this->activeUser($account);

        if (! $user) {
            return [];
        }

        if ($user->is_super_admin) {
            return Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        $user->loadMissing(['role.permissionScopes', 'permissions']);

        $scopeNames = $user->role->permissionScopes
            ->pluck('name')
            ->all();
        $directNames = $user->permissions
            ->pluck('name')
            ->all();

        $names = array_values(array_intersect($directNames, $scopeNames));
        sort($names);

        return $names;
    }

    private function activeUser(AuthAccount $account): ?User
    {
        $account->loadMissing('user.role');
        $user = $account->user;

        if (
            ! $account->is_active
            || ! $user?->is_active
            || ! $user->role?->is_active
        ) {
            return null;
        }

        return $user;
    }
}
