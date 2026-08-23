<?php

namespace Tests;

use App\Models\AuthAccount;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected string $validPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->validPassword = Str::random(32);
    }

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $roleAttributes
     */
    protected function createStaffAccount(
        array $accountAttributes = [],
        array $userAttributes = [],
        array $roleAttributes = [],
        int $branchCount = 1,
    ): AuthAccount {
        $role = Role::factory()->create($roleAttributes);
        $user = User::factory()->create([
            ...$userAttributes,
            'role_code' => $role->code,
        ]);
        $account = AuthAccount::factory()->create([
            ...$accountAttributes,
            'user_code' => $user->code,
            'password' => $accountAttributes['password'] ?? $this->validPassword,
        ]);

        if ($branchCount > 0) {
            $branches = Branch::factory()->count($branchCount)->create();
            $user->branches()->attach($branches);
        }

        return $account->load('user.role', 'user.branches');
    }

    /**
     * @param  list<string>  $names
     * @return list<Permission>
     */
    protected function grantPermissions(AuthAccount $account, array $names): array
    {
        $expanded = PermissionDependency::expandNames($names);

        $permissions = collect($expanded)->map(
            fn (string $name): Permission => $this->findOrCreatePermission($name),
        );

        $codes = $permissions->pluck('code')->all();
        $account->user->role->permissionScopes()->syncWithoutDetaching($codes);
        $account->user->permissions()->syncWithoutDetaching($codes);

        return $permissions->all();
    }

    protected function grantDashboardPermission(AuthAccount $account): Permission
    {
        return $this->grantPermissions($account, ['dashboard.view'])[0];
    }

    private function findOrCreatePermission(string $name): Permission
    {
        return Permission::query()->where('name', $name)->first()
            ?? Permission::factory()->create(['name' => $name]);
    }
}
