<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | One row per translation key (namespace + group + key) holding the values
    | read from the lang files (`source`) and the database overrides (`text`).
    |
    */
    'table' => 'translation_lines',

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The locales to scan and manage. Empty = every locale found in lang_path()
    | (`lang/{locale}/` folders and `lang/{locale}.json` files) plus app.locale
    | and app.fallback_locale.
    |
    */
    'locales' => [],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | Package namespaces (`filament-panels::`, `validation` stays app-level) the
    | scanner reads. `include` empty = every registered namespace; `exclude`
    | always wins.
    |
    */
    'namespaces' => [
        'include' => [],
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Overrides are cached per namespace + group and flushed whenever a line is
    | saved. null = the default cache store.
    |
    */
    'cache_store' => null,
];
