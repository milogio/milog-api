<?php

namespace App\Providers;

use App\Services\MiLog\TimelineEventFormatter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TimelineEventFormatter::class, function ($app) {
            return new TimelineEventFormatter($app, config('milog.formatters', []));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
