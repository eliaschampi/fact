<?php

namespace App\Support\Authorization;

use App\Models\AuthAccount;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

    /** @return Builder<User> */
    public function usersWithEffectivePermission(string $permissionName): Builder
    {
        return $this->activeUsers()
            ->where(function (Builder $query) use ($permissionName): void {
                $query->where('is_super_admin', true)
                    ->orWhere(function (Builder $query) use ($permissionName): void {
                        $query->whereHas(
                            'permissions',
                            fn (Builder $permission) => $permission->where('name', $permissionName),
                        )->whereHas(
                            'role.permissionScopes',
                            fn (Builder $permission) => $permission->where('name', $permissionName),
                        );
                    });
            });
    }

    public function requestedCashier(AuthAccount $account, User $actor): User
    {
        $requested = $this->request->query('cashier');

        if (! is_string($requested) || $requested === '' || $requested === $actor->code) {
            return $actor;
        }

        Gate::forUser($account)->authorize('cashbox.supervise');

        return $this->cashiersForSupervision()->findOrFail($requested);
    }

    /** @return Builder<User> */
    public function cashiersForSupervision(): Builder
    {
        return User::query()->where(function (Builder $query): void {
            $query->whereIn(
                'users.code',
                $this->usersWithEffectivePermission('cashbox.manage')->select('users.code'),
            )->orWhereIn(
                'users.code',
                DB::table('cashbox_movements')->select('cashier_user_code'),
            );
        });
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

    /** @return Builder<User> */
    private function activeUsers(): Builder
    {
        return User::query()
            ->where('users.is_active', true)
            ->whereHas('authAccount', fn (Builder $account) => $account->where('is_active', true))
            ->whereHas('role', fn (Builder $role) => $role->where('is_active', true));
    }
}
