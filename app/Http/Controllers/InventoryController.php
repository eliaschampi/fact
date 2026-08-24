<?php

namespace App\Http\Controllers;

use App\Models\AuthAccount;
use App\Models\ProductVariant;
use App\Support\Branches\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController extends Controller
{
    private const int PAGE_SIZE = 20;

    public function __construct(private readonly BranchContext $branchContext) {}

    public function index(Request $request): Response
    {
        Gate::authorize('inventory.view');

        /** @var AuthAccount $account */
        $account = $request->user();
        $branch = $this->branchContext->currentBranch($account);
        $search = trim((string) $request->query('q', ''));

        $variants = ProductVariant::query()
            ->with(['product' => fn ($query) => $query->select('code', 'name', 'is_active')])
            ->when(
                $branch !== null,
                fn ($query) => $query
                    ->where('issuer_code', $branch->issuer_code)
                    ->withSum(
                        ['balances as on_hand' => fn ($balances) => $balances->where('branch_code', $branch->code)],
                        'on_hand',
                    ),
                fn ($query) => $query->whereKey([]),
            )
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn ($product) => $product->where('name', 'ilike', "%{$search}%"),
                    ),
            ))
            ->orderBy('sku')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'balances' => $variants->through(fn (ProductVariant $variant): array => [
                'code' => $variant->code,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'unit_code' => $variant->unit_code,
                'cost_price' => $variant->cost_price,
                'price' => $variant->price,
                'on_hand' => (int) ($variant->on_hand ?? 0),
                'product' => [
                    'code' => $variant->product->code,
                    'name' => $variant->product->name,
                    'is_active' => $variant->product->is_active,
                ],
            ]),
            'filters' => ['q' => $search],
            'has_branch' => $branch !== null,
        ]);
    }
}
