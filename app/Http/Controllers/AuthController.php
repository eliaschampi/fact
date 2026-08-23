<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateAccount;
use App\Actions\LogoutAccount;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(
        LoginRequest $request,
        AuthenticateAccount $authenticateAccount,
    ): RedirectResponse {
        $authenticateAccount->handle(
            $request,
            $request->string('login')->toString(),
            $request->string('password')->toString(),
        );

        return to_route('branches.index');
    }

    public function destroy(Request $request, LogoutAccount $logoutAccount): RedirectResponse
    {
        $logoutAccount->handle($request);

        return to_route('login');
    }
}
