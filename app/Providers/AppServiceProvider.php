<?php

namespace App\Providers;

use App\Console\Commands\NativeMigrateFreshCommand;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Commands\FreshCommand as NativeFreshCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override NativePHP's FreshCommand with a local variant that avoids
        // duplicate command-name metadata in Symfony/Laravel command discovery.
        $this->app->singleton(NativeFreshCommand::class, function ($app) {
            return new NativeMigrateFreshCommand($app['migrator']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
