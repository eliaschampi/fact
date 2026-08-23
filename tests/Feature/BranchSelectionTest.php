<?php

namespace Tests\Feature;

use Tests\TestCase;

class BranchSelectionTest extends TestCase
{
    public function test_staff_can_switch_workspace_branch(): void
    {
        $account = $this->createStaffAccount(branchCount: 2);
        $target = $account->user->branches->last();

        $this->actingAs($account)
            ->put('/current-branch', ['branch_code' => $target->code])
            ->assertRedirect(route('branches.index'));

        $this->assertSame($target->code, session('current_branch_code'));
        $this->assertSame($target->code, $account->user->fresh()->preferred_branch_code);
    }

    public function test_staff_cannot_select_an_unassigned_branch(): void
    {
        $account = $this->createStaffAccount();
        $foreign = $this->createStaffAccount()->user->branches->sole();

        $this->actingAs($account)
            ->put('/current-branch', ['branch_code' => $foreign->code])
            ->assertForbidden();
    }
}
