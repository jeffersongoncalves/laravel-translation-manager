<?php

namespace JeffersonGoncalves\TranslationManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\TranslationManager\TranslationManager
 */
class TranslationManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-translation-manager';
    }
}
