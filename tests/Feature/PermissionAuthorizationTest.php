<?php

namespace Tests\Feature;

use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    public function test_home_requires_dashboard_view(): void
    {
        $account = $this->createStaffAccount();

        $this->actingAs($account)
            ->get('/')
            ->assertForbidden();
    }

    public function test_home_renders_when_dashboard_view_is_granted(): void
    {
        $account = $this->createStaffAccount();
        $this->grantDashboardPermission($account);

        $this->actingAs($account)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_super_admin_can_open_home_without_direct_grants(): void
    {
        $this->seed();

        $account = $this->createStaffAccount(userAttributes: ['is_super_admin' => true]);

        $this->actingAs($account)
            ->get('/')
            ->assertOk();
    }
}
