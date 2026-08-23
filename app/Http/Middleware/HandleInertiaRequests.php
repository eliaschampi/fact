<?php

namespace App\Http\Middleware;

use App\Models\AuthAccount;
use App\Support\Authorization\PermissionResolver;
use App\Support\Branches\BranchContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly BranchContext $branchContext,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $this->authenticationData($request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authenticationData(Request $request): ?array
    {
        $account = $request->user();

        if (! $account instanceof AuthAccount) {
            return null;
        }

        $account->loadMissing('user.role');
        $user = $account->user;

        if (! $user) {
            return null;
        }

        $branches = $this->branchContext->authorizedBranches($account);
        $currentBranch = $this->branchContext->currentBranch($account);

        return [
            'user' => [
                'code' => $user->code,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role_name' => $user->role->name,
            ],
            'branches' => $branches
                ->map(fn ($branch) => [
                    'code' => $branch->code,
                    'name' => $branch->name,
                ])
                ->values()
                ->all(),
            'current_branch' => $currentBranch
                ? [
                    'code' => $currentBranch->code,
                    'name' => $currentBranch->name,
                ]
                : null,
            'permissions' => $this->permissionResolver->effectiveNames($account),
        ];
    }
}
