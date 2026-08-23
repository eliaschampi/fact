<?php

namespace App\Providers;

use App\Models\AuthAccount;
use App\Support\Authorization\PermissionResolver;
use App\Support\Sunat\CacheTokenStore;
use App\Support\Sunat\CpeGateway;
use App\Support\Sunat\GreenterCpeGateway;
use App\Support\Sunat\GreenterGreGateway;
use App\Support\Sunat\GreGateway;
use Greenter\Services\Api\TokenStoreInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenStoreInterface::class, CacheTokenStore::class);
        $this->app->singleton(CpeGateway::class, GreenterCpeGateway::class);
        $this->app->singleton(GreGateway::class, GreenterGreGateway::class);
    }

    public function boot(): void
    {
        Gate::before(
            fn (AuthAccount $account, string $ability): ?bool => app(PermissionResolver::class)
                ->can($account, $ability) ? true : null,
        );
    }
}
