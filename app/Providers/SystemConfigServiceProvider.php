<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SystemConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $systemConfig = Config::get('System.SystemConfig.system');

        $this->app->singleton('system_config', function () use ($systemConfig) {
            return $systemConfig;
        });
    }
}
