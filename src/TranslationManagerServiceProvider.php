<?php

namespace JeffersonGoncalves\TranslationManager;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use JeffersonGoncalves\TranslationManager\Commands\ExportCommand;
use JeffersonGoncalves\TranslationManager\Commands\ScanCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TranslationManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-translation-manager')
            ->hasConfigFile('translation-manager')
            ->hasCommands([ScanCommand::class, ExportCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TranslationManager::class);
        $this->app->alias(TranslationManager::class, 'laravel-translation-manager');

        $this->app->extend('translation.loader', fn (Loader $loader, Application $app) => new DatabaseLoader($loader, $app->make(TranslationManager::class)));
    }

    public function packageBooted(): void
    {
        $migrations = __DIR__.'/../database/migrations';

        $this->loadMigrationsFrom($migrations);
        $this->publishes([$migrations => database_path('migrations')], 'translation-manager-migrations');
    }
}
