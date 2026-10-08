## Laravel Translation Manager

Database overrides for Laravel translations (app groups, JSON and package namespaces), laid over the lang files at runtime, with export back to `lang/`.

### Installation

@verbatim
<code-snippet name="Install" lang="bash">
composer require jeffersongoncalves/laravel-translation-manager
php artisan migrate
php artisan translation-manager:scan
</code-snippet>
@endverbatim

### Usage

@verbatim
<code-snippet name="Override a line" lang="php">
use JeffersonGoncalves\TranslationManager\Models\TranslationLine;

TranslationLine::query()->updateOrCreate(
    ['namespace' => '*', 'group' => 'messages', 'key' => 'welcome'],
    ['text' => ['pt_BR' => 'Olá!']],
);

TranslationLine::query()->missing('pt_BR')->get();
</code-snippet>
@endverbatim

### Rules
- `namespace` is `*` for app lines, `group` is `*` for JSON lines; PHP group keys are dotted (`nav.home`).
- `source` holds the file values (written only by `translation-manager:scan`); write overrides to `text` only.
- Save through the model (not query-builder updates) so the override cache is flushed.
- `translation-manager:export` writes overrides to `lang/{locale}.json`, `lang/{locale}/{group}.php` and `lang/vendor/{namespace}/{locale}/{group}.php`; `--clear` removes the exported overrides.
