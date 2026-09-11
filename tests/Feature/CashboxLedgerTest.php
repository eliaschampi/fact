<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\CashboxMovement;
use App\Models\CashboxTransfer;
use App\Models\CashOutflow;
use App\Models\CashOutflowReturn;
use App\Support\Cashbox\CashboxPosition;
use App\Support\LimaDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashboxLedgerTest extends TestCase
{
    public function test_cashbox_requires_view_and_writes_require_a_working_branch(): void
    {
        $account = $this->createStaffAccount(branchCount: 2);

        $this->actingAs($account)
            ->get('/cashbox')
            ->assertForbidden();

        $this->grantPermissions($account, ['cashbox.manage']);

        $this->actingAs($account)
            ->get('/cashbox')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cashbox/Index')
                ->where('can_write', true)
                ->where('can_manage', false)
                ->where('has_branch', false));

        $this->actingAs($account)
            ->post('/cashbox/incomes', $this->incomePayload())
            ->assertSessionHasErrors('branch');
    }

    public function test_cash_facts_produce_an_exact_daily_position_without_mutating_outflow(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-03 10:15:00', LimaDate::TIMEZONE));
        $account = $this->cashierAccount();
        $session = $this->currentBranchSession($account);

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', $this->incomePayload(amount: '100.00'))
            ->assertRedirect(route('cashbox.index', ['date' => '2026-09-03']));

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/outflows', $this->outflowPayload(amount: '30.00'))
            ->assertRedirect();

        $outflow = CashOutflow::query()->sole();

        $this->actingAs($account)
            ->withSession($session)
            ->post(
                "/cashbox/outflows/{$outflow->code}/returns",
                $this->returnPayload(amount: '5.00'),
            )
            ->assertRedirect();

        $this->assertMatchesRegularExpression('/^OUT-\d{6}$/', $outflow->outflow_number);
        $this->assertSame('30.00', $outflow->fresh()->amount);
        $this->assertSame('5.00', CashOutflowReturn::query()->sole()->amount);
        $this->assertSame(75_00, CashboxPosition::balanceInCents($account->user_code));

        $this->actingAs($account)
            ->get('/cashbox?date=2026-09-03')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.opening_amount', '0.00')
                ->where('summary.income_amount', '105.00')
                ->where('summary.expense_amount', '30.00')
                ->where('summary.current_amount', '75.00')
                ->has('movements.data', 3));
    }

    public function test_request_keys_replay_the_same_fact_and_reject_changed_or_cross_source_payloads(): void
    {
        $account = $this->cashierAccount();
        $session = $this->currentBranchSession($account);
        $key = (string) Str::uuid();
        $payload = $this->incomePayload($key, '40.00');

        $this->actingAs($account)->withSession($session)->post('/cashbox/incomes', $payload);
        $this->actingAs($account)->withSession($session)->post('/cashbox/incomes', $payload);

        $this->assertSame(1, CashboxMovement::query()->count());

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', [...$payload, 'amount' => '41.00'])
            ->assertSessionHasErrors('request_key');

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/outflows', $this->outflowPayload($key, '10.00'))
            ->assertSessionHasErrors('request_key');

        $this->assertSame(0, CashOutflow::query()->count());
    }

    public function test_cash_cannot_go_negative_and_an_income_cannot_be_reversed_after_it_is_spent(): void
    {
        $account = $this->cashierAccount();
        $session = $this->currentBranchSession($account);

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', $this->incomePayload(amount: '10.00'));
        $income = CashboxMovement::query()->sole();

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/outflows', $this->outflowPayload(amount: '8.00'));

        $this->actingAs($account)
            ->post("/cashbox/incomes/{$income->code}/reverse", ['reason' => 'Registro duplicado'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/outflows', $this->outflowPayload(amount: '2.01'))
            ->assertSessionHasErrors('amount');

        $this->assertSame(CashboxMovement::STATUS_ACTIVE, $income->fresh()->status);
        $this->assertSame(2_00, CashboxPosition::balanceInCents($account->user_code));
        $this->assertSame(1, CashOutflow::query()->count());
    }

    public function test_outflow_reversal_preserves_and_reverses_its_expense_and_return_facts(): void
    {
        $account = $this->cashierAccount();
        $session = $this->currentBranchSession($account);

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', $this->incomePayload(amount: '50.00'));
        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/outflows', $this->outflowPayload(amount: '20.00'));
        $outflow = CashOutflow::query()->sole();
        $this->actingAs($account)
            ->withSession($session)
            ->post(
                "/cashbox/outflows/{$outflow->code}/returns",
                $this->returnPayload(amount: '4.00'),
            );

        $this->actingAs($account)
            ->post("/cashbox/outflows/{$outflow->code}/reverse", [
                'reason' => 'El gasto no correspondió',
            ])
            ->assertRedirect();

        $this->assertSame(CashOutflow::STATUS_REVERSED, $outflow->fresh()->status);
        $this->assertSame(2, $outflow->movements()->where('status', 'reversed')->count());
        $this->assertSame(1, CashOutflowReturn::query()->count());
        $this->assertSame(50_00, CashboxPosition::balanceInCents($account->user_code));
    }

    public function test_transfer_is_atomic_for_both_cashiers_and_reversal_protects_the_recipient_balance(): void
    {
        $source = $this->cashierAccount();
        $destination = $this->cashierForSameIssuer($source);
        $sourceSession = $this->currentBranchSession($source);
        $destinationSession = $this->currentBranchSession($destination);

        $this->actingAs($source)
            ->withSession($sourceSession)
            ->post('/cashbox/incomes', $this->incomePayload(amount: '50.00'));

        $key = (string) Str::uuid();
        $payload = $this->transferPayload($destination, $key, '20.00');
        $this->actingAs($source)->withSession($sourceSession)->post('/cashbox/transfers', $payload);
        $this->actingAs($source)->withSession($sourceSession)->post('/cashbox/transfers', $payload);

        $transfer = CashboxTransfer::query()->sole();
        $this->assertMatchesRegularExpression('/^CTX-\d{6}$/', $transfer->transfer_number);
        $this->assertSame(2, $transfer->movements()->count());
        $this->assertSame(30_00, CashboxPosition::balanceInCents($source->user_code));
        $this->assertSame(20_00, CashboxPosition::balanceInCents($destination->user_code));

        $this->actingAs($destination)
            ->withSession($destinationSession)
            ->post('/cashbox/outflows', $this->outflowPayload(amount: '15.00'));

        $this->actingAs($source)
            ->post("/cashbox/transfers/{$transfer->code}/reverse", [
                'reason' => 'Se eligió otro cajero',
            ])
            ->assertSessionHasErrors('amount');

        $destinationOutflow = CashOutflow::query()
            ->where('cashier_user_code', $destination->user_code)
            ->sole();
        $this->actingAs($destination)
            ->post("/cashbox/outflows/{$destinationOutflow->code}/reverse", [
                'reason' => 'Gasto registrado por error',
            ]);
        $this->actingAs($source)
            ->post("/cashbox/transfers/{$transfer->code}/reverse", [
                'reason' => 'Se eligió otro cajero',
            ])
            ->assertRedirect();

        $this->assertSame(CashboxTransfer::STATUS_REVERSED, $transfer->fresh()->status);
        $this->assertSame(50_00, CashboxPosition::balanceInCents($source->user_code));
        $this->assertSame(0, CashboxPosition::balanceInCents($destination->user_code));
    }

    public function test_supervision_is_read_only_and_a_write_query_never_impersonates_the_selected_cashier(): void
    {
        $cashier = $this->cashierAccount();
        $supervisor = $this->cashierForSameIssuer($cashier);
        $this->grantPermissions($supervisor, ['cashbox.supervise']);

        $this->actingAs($cashier)
            ->withSession($this->currentBranchSession($cashier))
            ->post('/cashbox/incomes', $this->incomePayload(amount: '12.00'));

        $this->actingAs($supervisor)
            ->withSession($this->currentBranchSession($supervisor))
            ->get("/cashbox?cashier={$cashier->user_code}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cashier.code', $cashier->user_code)
                ->where('is_supervising', true)
                ->where('can_manage', false)
                ->where('summary.current_amount', '12.00'));

        $this->actingAs($supervisor)
            ->withSession($this->currentBranchSession($supervisor))
            ->post(
                "/cashbox/incomes?cashier={$cashier->user_code}",
                $this->incomePayload(amount: '3.00'),
            )
            ->assertRedirect();

        $this->assertSame(12_00, CashboxPosition::balanceInCents($cashier->user_code));
        $this->assertSame(3_00, CashboxPosition::balanceInCents($supervisor->user_code));

        $viewer = $this->cashierForSameIssuer($cashier);
        $this->grantPermissions($viewer, ['cashbox.view']);

        $this->actingAs($viewer)
            ->get("/cashbox?cashier={$cashier->user_code}")
            ->assertForbidden();
    }

    public function test_server_owns_the_business_date_and_month_view_carries_the_running_balance(): void
    {
        $account = $this->cashierAccount();
        $session = $this->currentBranchSession($account);
        $this->travelTo(CarbonImmutable::parse('2026-08-31 23:55:00', LimaDate::TIMEZONE));

        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', [
                ...$this->incomePayload(amount: '20.00'),
                'business_date' => '1999-01-01',
            ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-01 00:05:00', LimaDate::TIMEZONE));
        $this->actingAs($account)
            ->withSession($session)
            ->post('/cashbox/incomes', $this->incomePayload(amount: '5.00'));

        $this->assertSame(
            ['2026-08-31', '2026-09-01'],
            CashboxMovement::query()->orderBy('business_date')->pluck('business_date')
                ->map(fn ($date) => $date->toDateString())
                ->all(),
        );

        $this->actingAs($account)
            ->get('/cashbox?view=month&month=2026-09')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('month_totals.opening_amount', '20.00')
                ->where('month_totals.income_amount', '5.00')
                ->where('month_totals.current_amount', '25.00')
                ->has('month_days', 1));
    }

    public function test_database_rejects_non_cash_movement_types(): void
    {
        $account = $this->cashierAccount();
        $this->actingAs($account)
            ->withSession($this->currentBranchSession($account))
            ->post('/cashbox/incomes', $this->incomePayload());

        $this->expectException(QueryException::class);

        CashboxMovement::query()->sole()->update(['movement_type' => 'yape']);
    }

    private function cashierAccount(): AuthAccount
    {
        $account = $this->createStaffAccount();
        $this->grantPermissions($account, ['cashbox.manage']);

        return $account;
    }

    private function cashierForSameIssuer(AuthAccount $source): AuthAccount
    {
        $account = $this->createStaffAccount(branchCount: 0);
        $account->user->branches()->attach($source->user->branches->firstOrFail());
        $this->grantPermissions($account, ['cashbox.manage']);

        return $account->load('user.role', 'user.branches');
    }

    /** @return array{request_key: string, amount: string, concept: string, note: null} */
    private function incomePayload(?string $requestKey = null, string $amount = '25.00'): array
    {
        return [
            'request_key' => $requestKey ?? (string) Str::uuid(),
            'amount' => $amount,
            'concept' => 'Fondo operativo',
            'note' => null,
        ];
    }

    /** @return array{request_key: string, amount: string, concept: string, note: null} */
    private function outflowPayload(?string $requestKey = null, string $amount = '10.00'): array
    {
        return [
            'request_key' => $requestKey ?? (string) Str::uuid(),
            'amount' => $amount,
            'concept' => 'Movilidad local',
            'note' => null,
        ];
    }

    /** @return array{request_key: string, amount: string, note: null} */
    private function returnPayload(?string $requestKey = null, string $amount = '2.00'): array
    {
        return [
            'request_key' => $requestKey ?? (string) Str::uuid(),
            'amount' => $amount,
            'note' => null,
        ];
    }

    /** @return array{request_key: string, destination_cashier_code: string, amount: string, note: null} */
    private function transferPayload(
        AuthAccount $destination,
        ?string $requestKey = null,
        string $amount = '10.00',
    ): array {
        return [
            'request_key' => $requestKey ?? (string) Str::uuid(),
            'destination_cashier_code' => $destination->user_code,
            'amount' => $amount,
            'note' => null,
        ];
    }
}
