<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\ReceivePurchase;
use App\Actions\Purchases\RecordPurchase;
use App\Http\Requests\CancelPurchaseRequest;
use App\Http\Requests\RecordPurchaseRequest;
use App\Models\AuthAccount;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Support\Branches\BranchContext;
use App\Support\LimaDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseController extends Controller
{
    private const int PAGE_SIZE = 20;

    private const int CATALOG_LIMIT = 20;

    public function __construct(private readonly BranchContext $branchContext) {}

    public function index(Request $request): Response
    {
        Gate::authorize('purchases.view');

        /** @var AuthAccount $account */
        $account = $request->user();
        $branch = $this->branchContext->currentBranch($account);
        $status = (string) $request->query('status', 'all');

        $purchases = Purchase::query()
            ->with(['items.variant.product'])
            ->when(
                $branch !== null,
                fn ($query) => $query->where('branch_code', $branch->code),
                fn ($query) => $query->whereKey([]),
            )
            ->when(
                in_array($status, Purchase::STATUSES, true),
                fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('ordered_at')
            ->orderByDesc('created_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases->through(fn (Purchase $purchase): array => $this->row($purchase)),
            'filters' => [
                'status' => in_array($status, Purchase::STATUSES, true) ? $status : 'all',
            ],
            'has_branch' => $branch !== null,
            'can_manage' => Gate::check('purchases.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('purchases.manage');

        /** @var AuthAccount $account */
        $account = $request->user();
        $branch = $this->branchContext->requireCurrent($account);

        $search = trim((string) $request->query('q', ''));

        $catalog = ProductVariant::query()
            ->with(['product' => fn ($query) => $query->select('code', 'name', 'is_active')])
            ->where('issuer_code', $branch->issuer_code)
            ->whereHas('product', fn ($product) => $product->where('is_active', true))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn ($product) => $product->where('name', 'ilike', "%{$search}%"),
                    ),
            ))
            ->orderBy('sku')
            ->limit(self::CATALOG_LIMIT)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'code' => $variant->code,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'cost_price' => $variant->cost_price,
                'product' => [
                    'code' => $variant->product->code,
                    'name' => $variant->product->name,
                ],
            ])
            ->all();

        return Inertia::render('Purchases/Form', [
            'catalog' => $catalog,
            'filters' => ['q' => $search],
        ]);
    }

    public function store(
        RecordPurchaseRequest $request,
        RecordPurchase $recordPurchase,
    ): RedirectResponse {
        Gate::authorize('purchases.manage');

        /** @var AuthAccount $account */
        $account = $request->user();
        $branch = $this->branchContext->requireCurrent($account);
        $attributes = $request->purchaseAttributes();

        $purchase = $recordPurchase->handle(
            $branch,
            $account->user,
            $attributes['items'],
            $attributes['note'],
        );

        Inertia::flash('success', "Compra {$purchase->purchase_number} registrada en tránsito.");

        return to_route('purchases.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        Gate::authorize('purchases.view');

        /** @var AuthAccount $account */
        $account = $request->user();
        $this->assertAuthorizedBranch($account, $purchase);

        $purchase->load(['items.variant.product', 'branch', 'user']);

        return Inertia::render('Purchases/Show', [
            'purchase' => $this->detail($purchase),
            'can_manage' => Gate::check('purchases.manage'),
        ]);
    }

    public function receive(
        Request $request,
        Purchase $purchase,
        ReceivePurchase $receivePurchase,
    ): RedirectResponse {
        Gate::authorize('purchases.manage');

        /** @var AuthAccount $account */
        $account = $request->user();
        $this->assertAuthorizedBranch($account, $purchase);

        $purchase = $receivePurchase->handle($purchase);

        Inertia::flash('success', "Compra {$purchase->purchase_number} recibida. El stock ya está en la sede.");

        return to_route('purchases.show', $purchase);
    }

    public function cancel(
        CancelPurchaseRequest $request,
        Purchase $purchase,
        CancelPurchase $cancelPurchase,
    ): RedirectResponse {
        Gate::authorize('purchases.manage');

        /** @var AuthAccount $account */
        $account = $request->user();
        $this->assertAuthorizedBranch($account, $purchase);

        $purchase = $cancelPurchase->handle($purchase, $request->note());

        Inertia::flash('success', "Compra {$purchase->purchase_number} anulada.");

        return to_route('purchases.show', $purchase);
    }

    private function assertAuthorizedBranch(AuthAccount $account, Purchase $purchase): void
    {
        $allowed = $this->branchContext
            ->authorizedBranches($account)
            ->contains('code', $purchase->branch_code);

        abort_unless($allowed, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Purchase $purchase): array
    {
        return [
            'code' => $purchase->code,
            'purchase_number' => $purchase->purchase_number,
            'status' => $purchase->status,
            'ordered_at' => $purchase->ordered_at?->toDateString(),
            'item_count' => $purchase->items->sum('quantity'),
            'total_cost' => $purchase->totalCost(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Purchase $purchase): array
    {
        $items = $purchase->items
            ->sortBy('position')
            ->map(function ($item): array {
                $lineTotal = Money::fromCents($item->quantity * Money::toCents((string) $item->unit_cost));

                return [
                    'code' => $item->code,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $lineTotal,
                    'variant' => [
                        'code' => $item->variant->code,
                        'sku' => $item->variant->sku,
                        'name' => $item->variant->name,
                        'product_name' => $item->variant->product->name,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'code' => $purchase->code,
            'purchase_number' => $purchase->purchase_number,
            'status' => $purchase->status,
            'ordered_at' => $purchase->ordered_at?->toDateString(),
            'received_at' => $purchase->received_at?->timezone(LimaDate::TIMEZONE)->toDateTimeString(),
            'cancelled_at' => $purchase->cancelled_at?->timezone(LimaDate::TIMEZONE)->toDateTimeString(),
            'note' => $purchase->note,
            'branch_name' => $purchase->branch->name,
            'user_name' => $purchase->user->displayName(),
            'is_receivable' => $purchase->isReceivable(),
            'is_cancellable' => $purchase->isCancellable(),
            'items' => $items,
            'total_cost' => $purchase->totalCost(),
        ];
    }
}
