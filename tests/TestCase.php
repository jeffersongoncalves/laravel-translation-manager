<?php

namespace JeffersonGoncalves\TranslationManager\Tests;

use JeffersonGoncalves\TranslationManager\TranslationManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TranslationManagerServiceProvider::class,
        ];
    }
}
