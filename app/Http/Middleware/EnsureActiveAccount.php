<?php

namespace App\Http\Middleware;

use App\Actions\LogoutAccount;
use App\Models\AuthAccount;
use App\Support\Branches\BranchContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveAccount
{
    public function __construct(
        private readonly LogoutAccount $logoutAccount,
        private readonly BranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (! $account instanceof AuthAccount) {
            return $this->reject(
                $request,
                'Tu sesión ya no está disponible. Inicia sesión nuevamente.',
            );
        }

        $account->loadMissing('user.role');

        if (
            ! $account->is_active
            || ! $account->user?->is_active
            || ! $account->user->role?->is_active
        ) {
            return $this->reject(
                $request,
                'Tu sesión ya no está disponible. Inicia sesión nuevamente.',
            );
        }

        if ($this->branchContext->authorizedBranches($account)->isEmpty()) {
            return $this->reject(
                $request,
                'Tu cuenta no tiene una sede activa asignada. Contacta a un administrador.',
            );
        }

        $this->branchContext->currentBranch($account);

        return $next($request);
    }

    private function reject(Request $request, string $message): RedirectResponse
    {
        $this->logoutAccount->handle($request);

        return to_route('login')->withErrors([
            'login' => $message,
        ]);
    }
}
