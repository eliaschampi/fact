<?php

namespace App\Http\Controllers;

use App\Actions\Cashbox\RecordCashboxIncome;
use App\Actions\Cashbox\RecordCashboxTransfer;
use App\Actions\Cashbox\RecordCashOutflow;
use App\Actions\Cashbox\RecordCashOutflowReturn;
use App\Actions\Cashbox\ReverseCashboxIncome;
use App\Actions\Cashbox\ReverseCashboxTransfer;
use App\Actions\Cashbox\ReverseCashOutflow;
use App\Http\Requests\RecordCashboxIncomeRequest;
use App\Http\Requests\RecordCashboxTransferRequest;
use App\Http\Requests\RecordCashOutflowRequest;
use App\Http\Requests\RecordCashOutflowReturnRequest;
use App\Http\Requests\ReverseCashboxFactRequest;
use App\Models\AuthAccount;
use App\Models\CashboxMovement;
use App\Models\CashboxTransfer;
use App\Models\CashOutflow;
use App\Models\User;
use App\Support\Authorization\PermissionResolver;
use App\Support\Branches\BranchContext;
use App\Support\Cashbox\CashboxPosition;
use App\Support\LimaDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CashboxController extends Controller
{
    private const int PAGE_SIZE = 20;

    private const int DAY_ACTIVITY_LIMIT = 8;

    /** @var list<string> */
    private const array VIEWS = ['day', 'operations', 'month'];

    public function __construct(
        private readonly BranchContext $branchContext,
        private readonly PermissionResolver $permissions,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('cashbox.view');

        [$account, $actor] = $this->actor($request);
        $cashier = $this->permissions->requestedCashier($account, $actor);
        $branch = $this->branchContext->currentBranch($account);
        $view = (string) $request->query('view', 'day');
        $view = in_array($view, self::VIEWS, true) ? $view : 'day';
        $today = LimaDate::businessDate();
        $date = $this->date($request->query('date'), $today);
        $from = $this->date($request->query('from'), $date);
        $to = $this->date($request->query('to'), $date);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $month = $this->month($request->query('month'), substr($date, 0, 7));
        $supervising = $cashier->code !== $actor->code;
        $canManage = ! $supervising
            && Gate::check('cashbox.manage')
            && $branch !== null;
        $monthDays = $view === 'month' ? $this->monthDays($cashier, $month) : [];

        return Inertia::render('Cashbox/Index', [
            'cashier' => $this->userData($cashier),
            'cashiers' => Gate::check('cashbox.supervise')
                ? $this->cashiers($actor)
                : [],
            'transfer_recipients' => $canManage
                ? $this->transferRecipients($actor, $branch->issuer_code)
                : [],
            'filters' => [
                'view' => $view,
                'date' => $date,
                'from' => $from,
                'to' => $to,
                'month' => $month,
                'today' => $today,
            ],
            'summary' => $this->dailySummary($cashier, $date),
            'movements' => $this->movements($cashier, $actor, $view, $date, $from, $to, $canManage),
            'month_days' => $monthDays,
            'month_totals' => $this->monthTotals($cashier, $month, $monthDays),
            'can_manage' => $canManage,
            'can_write' => ! $supervising && Gate::check('cashbox.manage'),
            'can_supervise' => Gate::check('cashbox.supervise'),
            'has_branch' => $branch !== null,
            'is_supervising' => $supervising,
        ]);
    }

    public function storeIncome(
        RecordCashboxIncomeRequest $request,
        RecordCashboxIncome $recordIncome,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [$account, $actor] = $this->actor($request);
        $branch = $this->branchContext->requireCurrent($account);
        $attributes = $request->cashboxAttributes();

        $recordIncome->handle(
            $branch,
            $actor,
            $attributes['request_key'],
            $attributes['amount'],
            $attributes['concept'],
            $attributes['note'],
        );

        Inertia::flash('success', 'Efectivo agregado a tu caja.');

        return to_route('cashbox.index', ['date' => LimaDate::businessDate()]);
    }

    public function storeOutflow(
        RecordCashOutflowRequest $request,
        RecordCashOutflow $recordOutflow,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [$account, $actor] = $this->actor($request);
        $branch = $this->branchContext->requireCurrent($account);
        $attributes = $request->cashboxAttributes();

        $outflow = $recordOutflow->handle(
            $branch,
            $actor,
            $attributes['request_key'],
            $attributes['amount'],
            $attributes['concept'],
            $attributes['note'],
        );

        Inertia::flash('success', "Gasto {$outflow->outflow_number} registrado.");

        return to_route('cashbox.index', ['date' => LimaDate::businessDate()]);
    }

    public function storeReturn(
        RecordCashOutflowReturnRequest $request,
        CashOutflow $cashOutflow,
        RecordCashOutflowReturn $recordReturn,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [$account, $actor] = $this->actor($request);
        $branch = $this->branchContext->requireCurrent($account);
        $attributes = $request->cashboxAttributes();

        $recordReturn->handle(
            $branch,
            $actor,
            $cashOutflow,
            $attributes['request_key'],
            $attributes['amount'],
            $attributes['note'],
        );

        Inertia::flash('success', "Vuelto de {$cashOutflow->outflow_number} registrado.");

        return back();
    }

    public function storeTransfer(
        RecordCashboxTransferRequest $request,
        RecordCashboxTransfer $recordTransfer,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [$account, $actor] = $this->actor($request);
        $branch = $this->branchContext->requireCurrent($account);
        $attributes = $request->cashboxAttributes();

        $transfer = $recordTransfer->handle(
            $branch,
            $actor,
            $attributes['destination_cashier_code'],
            $attributes['request_key'],
            $attributes['amount'],
            $attributes['note'],
        );

        Inertia::flash('success', "Transferencia {$transfer->transfer_number} registrada.");

        return to_route('cashbox.index', ['date' => LimaDate::businessDate()]);
    }

    public function reverseIncome(
        ReverseCashboxFactRequest $request,
        CashboxMovement $cashboxMovement,
        ReverseCashboxIncome $reverseIncome,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [, $actor] = $this->actor($request);
        $reverseIncome->handle($actor, $cashboxMovement, $request->reason());

        Inertia::flash('success', 'Ingreso anulado sin borrar su historial.');

        return back();
    }

    public function reverseOutflow(
        ReverseCashboxFactRequest $request,
        CashOutflow $cashOutflow,
        ReverseCashOutflow $reverseOutflow,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [, $actor] = $this->actor($request);
        $reverseOutflow->handle($actor, $cashOutflow, $request->reason());

        Inertia::flash('success', "Gasto {$cashOutflow->outflow_number} anulado.");

        return back();
    }

    public function reverseTransfer(
        ReverseCashboxFactRequest $request,
        CashboxTransfer $cashboxTransfer,
        ReverseCashboxTransfer $reverseTransfer,
    ): RedirectResponse {
        Gate::authorize('cashbox.manage');
        [, $actor] = $this->actor($request);
        $reverseTransfer->handle($actor, $cashboxTransfer, $request->reason());

        Inertia::flash('success', "Transferencia {$cashboxTransfer->transfer_number} anulada.");

        return back();
    }

    /** @return array{AuthAccount, User} */
    private function actor(Request $request): array
    {
        /** @var AuthAccount $account */
        $account = $request->user();

        return [$account, $account->user()->firstOrFail()];
    }

    /** @return array{code: string, name: string} */
    private function userData(User $user): array
    {
        return ['code' => $user->code, 'name' => $user->displayName()];
    }

    /** @return list<array{code: string, name: string}> */
    private function cashiers(User $actor): array
    {
        $cashiers = $this->permissions->cashiersForSupervision()
            ->get(['users.code', 'users.first_name', 'users.last_name']);

        if (! $cashiers->contains('code', $actor->code)) {
            $cashiers->push($actor);
        }

        return $cashiers
            ->sortBy(fn (User $user): string => $user->displayName(), SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (User $user): array => $this->userData($user))
            ->values()
            ->all();
    }

    /** @return list<array{code: string, name: string}> */
    private function transferRecipients(User $actor, string $issuerCode): array
    {
        return $this->permissions
            ->usersWithEffectivePermission('cashbox.manage')
            ->whereKeyNot($actor->code)
            ->whereHas('branches', fn (Builder $query) => $query
                ->where('branches.issuer_code', $issuerCode)
                ->where('branches.is_active', true))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['users.code', 'users.first_name', 'users.last_name'])
            ->map(fn (User $user): array => $this->userData($user))
            ->all();
    }

    /** @return array<string, string> */
    private function dailySummary(User $cashier, string $date): array
    {
        $row = DB::table('cashbox_daily_summary')
            ->where('cashier_user_code', $cashier->code)
            ->where('business_date', $date)
            ->first();

        if ($row === null) {
            $balance = Money::fromCents(CashboxPosition::balanceInCents($cashier->code, $date));

            return [
                'opening_amount' => $balance,
                'income_amount' => '0.00',
                'expense_amount' => '0.00',
                'transfer_in_amount' => '0.00',
                'transfer_out_amount' => '0.00',
                'current_amount' => $balance,
            ];
        }

        return [
            'opening_amount' => Money::normalize((string) $row->opening_amount),
            'income_amount' => Money::normalize((string) $row->income_amount),
            'expense_amount' => Money::normalize((string) $row->expense_amount),
            'transfer_in_amount' => Money::normalize((string) $row->transfer_in_amount),
            'transfer_out_amount' => Money::normalize((string) $row->transfer_out_amount),
            'current_amount' => Money::normalize((string) $row->current_amount),
        ];
    }

    /** @return array<string, mixed> */
    private function movements(
        User $cashier,
        User $actor,
        string $view,
        string $date,
        string $from,
        string $to,
        bool $canManage,
    ): array {
        if ($view === 'month') {
            return $this->emptyPagination();
        }

        $query = CashboxMovement::query()
            ->with([
                'branch:code,name',
                'outflow.cashReturn',
                'transfer.sourceCashier:code,first_name,last_name',
                'transfer.destinationCashier:code,first_name,last_name',
            ])
            ->where('cashier_user_code', $cashier->code)
            ->when(
                $view === 'day',
                fn (Builder $query) => $query->where('business_date', $date),
                fn (Builder $query) => $query
                    ->where('business_date', '>=', $from)
                    ->where('business_date', '<=', $to),
            )
            ->orderByDesc('business_date')
            ->orderByDesc('created_at');

        if ($view === 'day') {
            $rows = $query->limit(self::DAY_ACTIVITY_LIMIT)->get();

            return [
                'data' => $rows
                    ->map(fn (CashboxMovement $movement): array => $this->movementData(
                        $movement,
                        $actor,
                        $canManage,
                    ))
                    ->all(),
                'current_page' => 1,
                'last_page' => 1,
                'total' => $rows->count(),
            ];
        }

        /** @var LengthAwarePaginator<CashboxMovement> $paginator */
        $paginator = $query->paginate(self::PAGE_SIZE)->withQueryString();

        return $paginator
            ->through(fn (CashboxMovement $movement): array => $this->movementData(
                $movement,
                $actor,
                $canManage,
            ))
            ->toArray();
    }

    /** @return array<string, mixed> */
    private function movementData(
        CashboxMovement $movement,
        User $actor,
        bool $canManage,
    ): array {
        $outflow = $movement->outflow;
        $transfer = $movement->transfer;
        $counterparty = null;
        $reference = null;
        $title = $movement->concept ?? 'Movimiento de efectivo';
        $detail = $movement->note;

        if ($outflow !== null) {
            $reference = $outflow->outflow_number;
            $title = $movement->movement_type === CashboxMovement::TYPE_OUTFLOW_RETURN
                ? 'Vuelto · '.$outflow->concept
                : $outflow->concept;
            $detail = $movement->movement_type === CashboxMovement::TYPE_OUTFLOW_RETURN
                ? $outflow->cashReturn?->note
                : $outflow->note;
        }

        if ($transfer !== null) {
            $reference = $transfer->transfer_number;
            $sent = $movement->direction === CashboxMovement::DIRECTION_OUT;
            $title = $sent ? 'Efectivo enviado' : 'Efectivo recibido';
            $counterparty = $sent
                ? $transfer->destinationCashier->displayName()
                : $transfer->sourceCashier->displayName();
            $detail = $transfer->note;
        }

        return [
            'code' => $movement->code,
            'owner_code' => $outflow?->code ?? $transfer?->code ?? $movement->code,
            'reference' => $reference,
            'movement_type' => $movement->movement_type,
            'direction' => $movement->direction,
            'amount' => $movement->amount,
            'business_date' => $movement->business_date?->toDateString(),
            'created_at' => $movement->created_at?->timezone(LimaDate::TIMEZONE)->toDateTimeString(),
            'title' => $title,
            'detail' => $detail,
            'counterparty' => $counterparty,
            'branch_name' => $movement->branch->name,
            'status' => $movement->status,
            'reversal_reason' => $movement->reversal_reason,
            'return_amount' => $outflow?->cashReturn?->amount,
            'can_return' => $canManage
                && $movement->movement_type === CashboxMovement::TYPE_EXPENSE
                && $movement->status === CashboxMovement::STATUS_ACTIVE
                && $outflow?->status === CashOutflow::STATUS_ACTIVE
                && $outflow->cashReturn === null,
            'can_reverse' => $canManage
                && $movement->status === CashboxMovement::STATUS_ACTIVE
                && (
                    $movement->movement_type === CashboxMovement::TYPE_MANUAL_INCOME
                    || $movement->movement_type === CashboxMovement::TYPE_EXPENSE
                    || (
                        $movement->movement_type === CashboxMovement::TYPE_TRANSFER
                        && $transfer?->source_cashier_user_code === $actor->code
                    )
                ),
        ];
    }

    /** @return list<array<string, string>> */
    private function monthDays(User $cashier, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, LimaDate::TIMEZONE);
        $end = $start->endOfMonth();

        return DB::table('cashbox_daily_summary')
            ->where('cashier_user_code', $cashier->code)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('business_date')
            ->get()
            ->map(fn (object $row): array => [
                'business_date' => (string) $row->business_date,
                'opening_amount' => Money::normalize((string) $row->opening_amount),
                'income_amount' => Money::normalize((string) $row->income_amount),
                'expense_amount' => Money::normalize((string) $row->expense_amount),
                'transfer_in_amount' => Money::normalize((string) $row->transfer_in_amount),
                'transfer_out_amount' => Money::normalize((string) $row->transfer_out_amount),
                'current_amount' => Money::normalize((string) $row->current_amount),
            ])
            ->all();
    }

    /**
     * @param  list<array<string, string>>  $days
     * @return array<string, string>
     */
    private function monthTotals(User $cashier, string $month, array $days): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, LimaDate::TIMEZONE);
        $opening = CashboxPosition::balanceInCents(
            $cashier->code,
            $start->subDay()->toDateString(),
        );
        $totals = [
            'income_amount' => 0,
            'expense_amount' => 0,
            'transfer_in_amount' => 0,
            'transfer_out_amount' => 0,
        ];

        foreach ($days as $day) {
            foreach ($totals as $name => $value) {
                $totals[$name] = $value + Money::toCents($day[$name]);
            }
        }

        $closing = $opening
            + $totals['income_amount']
            - $totals['expense_amount']
            + $totals['transfer_in_amount']
            - $totals['transfer_out_amount'];

        return [
            'opening_amount' => Money::fromCents($opening),
            'income_amount' => Money::fromCents($totals['income_amount']),
            'expense_amount' => Money::fromCents($totals['expense_amount']),
            'transfer_in_amount' => Money::fromCents($totals['transfer_in_amount']),
            'transfer_out_amount' => Money::fromCents($totals['transfer_out_amount']),
            'current_amount' => Money::fromCents($closing),
        ];
    }

    private function date(mixed $value, string $fallback): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, LimaDate::TIMEZONE);

            if ($date && $date->format('Y-m-d') === $value) {
                return $value;
            }
        }

        return $fallback;
    }

    private function month(mixed $value, string $fallback): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $value, LimaDate::TIMEZONE);

            if ($month && $month->format('Y-m') === $value) {
                return $value;
            }
        }

        return $fallback;
    }

    /** @return array{data: list<mixed>, current_page: int, last_page: int, total: int} */
    private function emptyPagination(): array
    {
        return ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0];
    }
}
