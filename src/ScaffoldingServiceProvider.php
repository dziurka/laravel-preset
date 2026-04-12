<?php

namespace Dziurka\LaravelPreset;

use Dziurka\LaravelPreset\Console\InstallCommand;
use Dziurka\LaravelPreset\Console\InstallDeployerCommand;
use Dziurka\LaravelPreset\Console\UpdateCommand;
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
            InstallDeployerCommand::class,
            UpdateCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../stubs' => base_path(),
        ], 'laravel-preset-stubs');
    }
}
