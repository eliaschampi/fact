<?php

namespace App\Actions;

use App\Models\AuthAccount;
use App\Support\Branches\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthenticateAccount
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function __construct(private readonly BranchContext $branchContext) {}

    public function handle(Request $request, string $login, string $password): AuthAccount
    {
        $normalizedLogin = Str::lower(trim($login));
        $throttleKey = $this->throttleKey($normalizedLogin, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Demasiados intentos. Inténtalo de nuevo en {$seconds} segundos.",
            ]);
        }

        $account = AuthAccount::query()
            ->with(['user.role'])
            ->where('login', $normalizedLogin)
            ->first();

        $passwordIsValid = Hash::check(
            $password,
            $account?->password ?? $this->dummyHash(),
        );

        if (! $passwordIsValid || ! $account?->is_active) {
            $this->fail($throttleKey);
        }

        $user = $account->user;

        if (
            ! $user?->is_active
            || ! $user->role?->is_active
        ) {
            $this->fail($throttleKey);
        }

        $branches = $this->branchContext->authorizedBranches($account);

        if ($branches->isEmpty()) {
            RateLimiter::clear($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'Tu cuenta no tiene una sede activa asignada. Contacta a un administrador.',
            ]);
        }

        $preferredBranch = $branches->firstWhere('code', $user->preferred_branch_code);

        if ($user->preferred_branch_code && ! $preferredBranch) {
            $user->update(['preferred_branch_code' => null]);
        }

        $currentBranchCode = $preferredBranch?->code
            ?? ($branches->count() === 1 ? $branches->sole()->code : null);

        $attributes = ['last_login_at' => now()];

        if (Hash::needsRehash($account->password)) {
            $attributes['password'] = $password;
        }

        $account->forceFill($attributes)->save();

        Auth::login($account);
        $request->session()->regenerate();
        $request->session()->forget('current_branch_code');

        if ($currentBranchCode !== null) {
            $request->session()->put('current_branch_code', $currentBranchCode);
        }

        RateLimiter::clear($throttleKey);

        return $account;
    }

    private function fail(string $throttleKey): never
    {
        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'login' => 'No pudimos iniciar sesión con esos datos.',
        ]);
    }

    private function throttleKey(string $login, ?string $ip): string
    {
        return 'account-login:'.hash('sha256', $login.'|'.($ip ?? 'unknown'));
    }

    private function dummyHash(): string
    {
        return (string) config('necofact.auth.dummy_password_hash');
    }
}
