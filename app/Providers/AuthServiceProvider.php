<?php

namespace App\Providers;

use DateInterval;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Passport::tokensCan([
            'ui' => 'Access the MiLog UI API for the token-bound tenant.',
        ]);
        Passport::tokensExpireIn(new DateInterval('PT'.config('milog.ui_auth.access_token_minutes', 15).'M'));
    }
}
