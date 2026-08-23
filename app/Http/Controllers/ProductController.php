<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\SaveProduct;
use App\Http\Requests\SaveProductRequest;
use App\Models\Issuer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    private const int PAGE_SIZE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('products.view');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        $variants = ProductVariant::query()
            ->with(['product' => fn ($query) => $query->select('code', 'name', 'is_active')])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn ($product) => $product->where('name', 'ilike', "%{$search}%"),
                    ),
            ))
            ->when(
                $status === 'active' || $status === 'inactive',
                fn ($query) => $query->whereHas(
                    'product',
                    fn ($product) => $product->where('is_active', $status === 'active'),
                ),
            )
            ->orderBy('sku')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'variants' => $variants->through(
                fn (ProductVariant $variant): array => [
                    'code' => $variant->code,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'unit_code' => $variant->unit_code,
                    'tax_affectation' => $variant->tax_affectation,
                    'price' => $variant->price,
                    'product' => [
                        'code' => $variant->product->code,
                        'name' => $variant->product->name,
                        'is_active' => $variant->product->is_active,
                    ],
                ],
            ),
            'filters' => [
                'q' => $search,
                'status' => $status === 'active' || $status === 'inactive' ? $status : 'all',
            ],
            'can_manage' => Gate::check('products.manage'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('products.manage');

        return Inertia::render('Products/Form', [
            'product' => null,
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $saveProduct): RedirectResponse
    {
        Gate::authorize('products.manage');

        $product = $saveProduct->handle(
            new Product(['issuer_code' => Issuer::query()->sole()->code]),
            $request->productAttributes(),
        );

        Inertia::flash('success', "Producto «{$product->name}» registrado.");

        return to_route('products.index');
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('products.manage');

        return Inertia::render('Products/Form', [
            'product' => $this->formData($product->loadMissing('variants')),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        Gate::authorize('products.manage');

        $saveProduct->handle($product, $request->productAttributes());

        Inertia::flash('success', "Producto «{$product->name}» actualizado.");

        return to_route('products.index');
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     is_active: bool,
     *     variants: list<array{code: string, sku: string, name: string|null, unit_code: string, tax_affectation: string, price: string}>
     * }
     */
    private function formData(Product $product): array
    {
        return [
            'code' => $product->code,
            'name' => $product->name,
            'description' => $product->description,
            'is_active' => $product->is_active,
            'variants' => $product->variants
                ->sortBy('sku')
                ->map(fn (ProductVariant $variant): array => [
                    'code' => $variant->code,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'unit_code' => $variant->unit_code,
                    'tax_affectation' => $variant->tax_affectation,
                    'price' => $variant->price,
                ])
                ->values()
                ->all(),
        ];
    }
}
