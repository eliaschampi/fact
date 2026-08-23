<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_active_staff_can_log_in(): void
    {
        $account = $this->createStaffAccount();
        $branch = $account->user->branches->sole();

        $response = $this->post('/login', [
            'login' => strtoupper($account->login),
            'password' => $this->validPassword,
        ]);

        $response
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('current_branch_code', $branch->code);
        $this->assertAuthenticatedAs($account);
        $this->assertNotNull($account->fresh()->last_login_at);
        $this->assertDatabaseHas('sessions', ['user_id' => $account->code]);
    }

    public function test_account_model_hashes_plain_passwords(): void
    {
        $account = $this->createStaffAccount();

        $this->assertNotSame($this->validPassword, $account->password);
        $this->assertTrue(Hash::check($this->validPassword, $account->password));
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_message(): void
    {
        $account = $this->createStaffAccount();

        $response = $this->post('/login', [
            'login' => $account->login,
            'password' => 'incorrect-'.$this->validPassword,
        ]);

        $response->assertSessionHasErrors([
            'login' => 'No pudimos iniciar sesión con esos datos.',
        ]);
        $this->assertGuest();
    }

    public function test_unknown_login_is_rejected_with_the_same_generic_message(): void
    {
        $response = $this->post('/login', [
            'login' => 'unknown-cashier',
            'password' => $this->validPassword,
        ]);

        $response->assertSessionHasErrors([
            'login' => 'No pudimos iniciar sesión con esos datos.',
        ]);
        $this->assertGuest();
    }

    public function test_unicode_login_cannot_overflow_the_database_rate_limit_key(): void
    {
        config()->set('cache.default', 'database');
        app()->forgetInstance(CacheRateLimiter::class);
        RateLimiter::clearResolvedInstance(CacheRateLimiter::class);

        $this->post('/login', [
            'login' => str_repeat('㌀', 100),
            'password' => $this->validPassword,
        ])->assertSessionHasErrors([
            'login' => 'No pudimos iniciar sesión con esos datos.',
        ]);

        $keys = DB::table('cache')->pluck('key');
        $this->assertNotEmpty($keys);
        $this->assertTrue(
            $keys->every(fn (string $key): bool => strlen($key) <= 255),
        );
        $this->assertGuest();
    }

    public function test_inactive_account_is_rejected(): void
    {
        $account = $this->createStaffAccount(['is_active' => false]);

        $this->post('/login', [
            'login' => $account->login,
            'password' => $this->validPassword,
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
