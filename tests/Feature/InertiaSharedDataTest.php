<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaSharedDataTest extends TestCase
{
    public function test_authenticated_pages_share_auth_and_permissions(): void
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['dashboard.view', 'branches.view']);

        $this->actingAs($account)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('auth.user.code', $account->user->code)
                ->where('auth.user.first_name', $account->user->first_name)
                ->has('auth.branches', 1)
                ->where('auth.permissions', fn ($permissions) => in_array(
                    'dashboard.view',
                    is_array($permissions) ? $permissions : $permissions->all(),
                    true,
                ))
            );
    }
}
