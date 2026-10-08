<div class="filament-hidden">

![Laravel Translation Manager](https://raw.githubusercontent.com/jeffersongoncalves/laravel-translation-manager/main/art/jeffersongoncalves-laravel-translation-manager.png)

</div>

# Laravel Translation Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-translation-manager.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-translation-manager)
[![Tests](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-translation-manager/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-translation-manager/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-translation-manager.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-translation-manager)

Override any Laravel translation from the database — your app's `lang/{locale}/*.php` groups, the `lang/{locale}.json` strings **and the lines of every installed package** (`filament-panels::`, `validation`, your plugins…) — without a deploy. Find the keys missing in each locale, and when you're happy, export the overrides back to `lang/` so they live in Git.

The lang files stay the source of truth: the package only lays the database overrides over what the framework's file loader returns. For an admin UI, use [jeffersongoncalves/filament-translation-manager](https://github.com/jeffersongoncalves/filament-translation-manager).

## Installation

```bash
composer require jeffersongoncalves/laravel-translation-manager
php artisan migrate
php artisan translation-manager:scan
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=translation-manager-config
```

## How it works

- `translation-manager:scan` reads every lang file — app groups, JSON, and the groups of each registered package namespace (including the ones you published to `lang/vendor`) — for every locale, and stores the values in the `translation_lines` table (`source` column). Re-run it after adding keys or installing packages; it never touches your overrides.
- An override is a value in the `text` column for a locale. `__()`, `trans()` and `@lang` return it instead of the file value. An empty override falls back to the file.
- Overrides are cached per namespace + group and the cache is flushed whenever a line is saved or deleted.
- If the table doesn't exist yet (fresh install, migrations pending) the files are used as-is — translations never break the app.
- Long-running processes (queue workers, Octane) keep the groups they already loaded — restart them after changing overrides.

## Usage

```php
use JeffersonGoncalves\TranslationManager\Models\TranslationLine;

// app group: lang/pt_BR/messages.php → messages.welcome
TranslationLine::query()->updateOrCreate(
    ['namespace' => '*', 'group' => 'messages', 'key' => 'welcome'],
    ['text' => ['pt_BR' => 'Olá!']],
);

// package line: filament-panels::pages/dashboard.title
TranslationLine::query()
    ->where(['namespace' => 'filament-panels', 'group' => 'pages/dashboard', 'key' => 'title'])
    ->first()
    ?->update(['text' => ['pt_BR' => 'Início']]);

// keys with neither a file value nor an override in a locale
TranslationLine::query()->missing('pt_BR')->get()->map->fullKey();
```

`namespace` is `*` for the app's own lines and `group` is `*` for JSON lines. `$line->value('pt_BR')` returns what `__()` would, `$line->fullKey()` the key you'd pass to it.

## Export to lang files

```bash
php artisan translation-manager:export                 # every locale
php artisan translation-manager:export --locale=pt_BR  # one locale
php artisan translation-manager:export --clear         # and drop the exported overrides from the database
```

| Override | Written to |
|---|---|
| JSON line | `lang/{locale}.json` |
| App group | `lang/{locale}/{group}.php` |
| Package line | `lang/vendor/{namespace}/{locale}/{group}.php` — the file Laravel already lays over the package's own |

Existing keys in those files are kept; only the overridden keys are added or replaced.

## Configuration

| Key | Default | |
|---|---|---|
| `table` | `translation_lines` | |
| `locales` | `[]` | locales to scan and manage; empty = every locale found in `lang/` plus `app.locale` and `app.fallback_locale` |
| `namespaces.include` | `[]` | package namespaces to scan; empty = all |
| `namespaces.exclude` | `[]` | package namespaces to skip |
| `cache_store` | `null` | cache store for the overrides; `null` = default |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
