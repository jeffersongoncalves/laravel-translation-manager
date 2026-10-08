<?php

namespace JeffersonGoncalves\TranslationManager\Tests;

use Illuminate\Support\Facades\File;
use JeffersonGoncalves\TranslationManager\TranslationManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $langPath;

    protected function getPackageProviders($app): array
    {
        return [
            TranslationManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // A throwaway copy of the fixtures: export writes into lang/.
        $this->langPath = sys_get_temp_dir().'/translation-manager-'.uniqid();
        File::copyDirectory(__DIR__.'/fixtures/lang', $this->langPath);
        $app->useLangPath($this->langPath);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Like a package's loadTranslationsFrom(): registered only once the translator resolves.
        $this->app->afterResolving('translator', fn ($translator) => $translator->addNamespace('demo', __DIR__.'/fixtures/package'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }
}
