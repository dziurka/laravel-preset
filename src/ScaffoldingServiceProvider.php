<?php

namespace Dziurka\LaravelPreset;

use Dziurka\LaravelPreset\Console\InstallCommand;
use Illuminate\Support\ServiceProvider;

class ScaffoldingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../stubs' => base_path(),
        ], 'laravel-preset-stubs');
    }
}
