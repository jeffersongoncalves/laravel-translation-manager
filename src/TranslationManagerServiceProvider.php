<?php

namespace JeffersonGoncalves\TranslationManager;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TranslationManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-translation-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
