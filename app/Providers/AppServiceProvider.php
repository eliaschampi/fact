<?php

namespace App\Providers;

use App\Models\AuthAccount;
use App\Support\Authorization\PermissionResolver;
use App\Support\Sunat\CacheTokenStore;
use App\Support\Sunat\CpeGateway;
use App\Support\Sunat\GreenterCpeGateway;
use App\Support\Sunat\GreenterGreGateway;
use App\Support\Sunat\GreGateway;
use App\Support\Sunat\SunatEndpoints;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CpeGateway::class, GreenterCpeGateway::class);
        // Greenter surface stays inside Support/Sunat: the provider wires
        // concretes only, so no Greenter import is needed here.
        $this->app->singleton(GreGateway::class, fn (): GreenterGreGateway => new GreenterGreGateway(
            app(SunatEndpoints::class),
            app(CacheTokenStore::class),
        ));
    }

    public function boot(): void
    {
        Gate::before(
            fn (AuthAccount $account, string $ability): ?bool => app(PermissionResolver::class)
                ->can($account, $ability) ? true : null,
        );
    }
}
