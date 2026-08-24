<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Issuer;
use Illuminate\Database\QueryException;
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

    public function test_establishment_code_is_unique_per_issuer(): void
    {
        $issuer = Issuer::factory()->create();
        Branch::factory()->for($issuer)->create(['sunat_establishment_code' => '0001']);

        $this->expectException(QueryException::class);

        Branch::factory()->for($issuer)->create(['sunat_establishment_code' => '0001']);
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
