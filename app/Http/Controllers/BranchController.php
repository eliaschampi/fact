<?php

namespace App\Http\Controllers;

use App\Actions\SelectBranch;
use App\Http\Requests\SelectBranchRequest;
use App\Models\AuthAccount;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BranchController extends Controller
{
    public function index(): Response
    {
        $canViewCatalog = Gate::check('branches.view');

        $catalog = [];

        if ($canViewCatalog) {
            $catalog = Branch::query()
                ->withCount('users')
                ->orderBy('name')
                ->get(['code', 'name', 'is_active', 'sunat_establishment_code'])
                ->map(fn (Branch $branch): array => [
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'is_active' => $branch->is_active,
                    'sunat_establishment_code' => $branch->sunat_establishment_code,
                    'users_count' => $branch->users_count,
                ])
                ->all();
        }

        return Inertia::render('Branches/Index', [
            'catalog' => $catalog,
            'can_view_catalog' => $canViewCatalog,
        ]);
    }

    public function update(
        SelectBranchRequest $request,
        SelectBranch $selectBranch,
    ): RedirectResponse {
        /** @var AuthAccount $account */
        $account = $request->user();

        $selectBranch->handle(
            $account,
            $request->string('branch_code')->toString(),
            $request->session(),
        );

        return to_route('branches.index');
    }
}
