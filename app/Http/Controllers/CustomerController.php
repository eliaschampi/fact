<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\SaveCustomer;
use App\Http\Requests\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\Issuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    private const int PAGE_SIZE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('customers.view');

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('doc_number', 'ilike', "{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%"),
            ))
            ->orderBy('doc_number')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers->through(
                fn (Customer $customer): array => [
                    'code' => $customer->code,
                    'doc_type' => $customer->doc_type,
                    'doc_number' => $customer->doc_number,
                    'name' => $customer->name,
                    'is_final_consumer' => $customer->isFinalConsumer(),
                ],
            ),
            'filters' => ['q' => $search],
            'can_manage' => Gate::check('customers.manage'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('customers.manage');

        return Inertia::render('Customers/Form', [
            'customer' => null,
        ]);
    }

    public function store(SaveCustomerRequest $request, SaveCustomer $saveCustomer): RedirectResponse
    {
        Gate::authorize('customers.manage');

        $customer = $saveCustomer->handle(
            new Customer(['issuer_code' => Issuer::query()->sole()->code]),
            $request->customerAttributes(),
        );

        Inertia::flash('success', "Cliente «{$customer->name}» registrado.");

        return to_route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('customers.manage');

        return Inertia::render('Customers/Form', [
            'customer' => [
                'code' => $customer->code,
                'doc_type' => $customer->doc_type,
                'doc_number' => $customer->doc_number,
                'name' => $customer->name,
            ],
        ]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer, SaveCustomer $saveCustomer): RedirectResponse
    {
        Gate::authorize('customers.manage');

        $saveCustomer->handle($customer, $request->customerAttributes());

        Inertia::flash('success', "Cliente «{$customer->name}» actualizado.");

        return to_route('customers.index');
    }
}
