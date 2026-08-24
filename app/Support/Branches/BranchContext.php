<?php

namespace App\Support\Branches;

use App\Models\AuthAccount;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BranchContext
{
    private const string BRANCHES_CACHE_KEY = self::class.'.authorized_branches';

    public function __construct(private readonly Request $request) {}

    /**
     * @return Collection<int, Branch>
     */
    public function authorizedBranches(AuthAccount $account): Collection
    {
        $cacheKey = self::BRANCHES_CACHE_KEY.'.'.$account->getKey();

        if ($this->request->attributes->has($cacheKey)) {
            /** @var Collection<int, Branch> */
            return $this->request->attributes->get($cacheKey);
        }

        $branches = $account->user?->branches()
            ->active()
            ->orderBy('branches.name')
            ->get(['branches.code', 'branches.name', 'branches.issuer_code'])
            ?? collect();

        $this->request->attributes->set($cacheKey, $branches);

        return $branches;
    }

    public function currentBranch(AuthAccount $account): ?Branch
    {
        $branches = $this->authorizedBranches($account);
        $currentCode = $this->request->session()->get('current_branch_code');
        $current = is_string($currentCode)
            ? $branches->firstWhere('code', $currentCode)
            : null;

        if ($currentCode !== null && ! $current) {
            $this->request->session()->forget('current_branch_code');
        }

        if (! $current && $branches->count() === 1) {
            $current = $branches->sole();
            $this->request->session()->put('current_branch_code', $current->code);
        }

        return $current;
    }

    public function requireCurrent(AuthAccount $account): Branch
    {
        $branch = $this->currentBranch($account);

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch' => 'Selecciona una sede de trabajo para continuar.',
            ]);
        }

        return $branch;
    }
}
