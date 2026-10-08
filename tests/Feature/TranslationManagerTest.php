<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\TranslationManager\DatabaseLoader;
use JeffersonGoncalves\TranslationManager\Models\TranslationLine;
use JeffersonGoncalves\TranslationManager\TranslationManager;

function line(string $namespace, string $group, string $key): TranslationLine
{
    return TranslationLine::query()->where(compact('namespace', 'group', 'key'))->firstOrFail();
}

it('wraps the translation loader', function () {
    expect(app('translation.loader'))->toBeInstanceOf(DatabaseLoader::class);
});

it('discovers the locales from lang/', function () {
    expect(app(TranslationManager::class)->locales())->toBe(['en', 'pt_BR']);

    config()->set('translation-manager.locales', ['es']);

    expect(app(TranslationManager::class)->locales())->toBe(['es']);
});

it('scans app, JSON and package lines without touching overrides', function () {
    $this->artisan('translation-manager:scan')->assertSuccessful();

    expect(line('*', 'messages', 'nav.home')->source)->toBe(['en' => 'Home', 'pt_BR' => 'Início'])
        ->and(line('*', '*', 'Save changes.')->source)->toBe(['pt_BR' => 'Salvar alterações.'])
        ->and(line('demo', 'actions', 'delete')->source)->toBe(['en' => 'Delete'])
        ->and(line('demo', 'actions', 'delete')->fullKey())->toBe('demo::actions.delete')
        ->and(line('demo', 'pages/dashboard', 'title')->fullKey())->toBe('demo::pages/dashboard.title');

    line('*', 'messages', 'welcome')->update(['text' => ['pt_BR' => 'Olá']]);
    app(TranslationManager::class)->scan();

    expect(line('*', 'messages', 'welcome')->text)->toBe(['pt_BR' => 'Olá']);
});

it('finds the lines missing in a locale', function () {
    app(TranslationManager::class)->scan();

    expect(TranslationLine::query()->missing('pt_BR')->get()->map->fullKey()->sort()->values()->all())
        ->toBe(['demo::actions.delete', 'demo::pages/dashboard.title', 'messages.nav.about']);

    line('*', 'messages', 'nav.about')->update(['text' => ['pt_BR' => 'Sobre']]);

    expect(TranslationLine::query()->missing('pt_BR')->count())->toBe(2);
});

it('serves database overrides for app, JSON and vendor lines', function () {
    app(TranslationManager::class)->scan();
    app()->setLocale('pt_BR');

    expect(__('messages.welcome'))->toBe('Bem-vindo');

    line('*', 'messages', 'welcome')->update(['text' => ['pt_BR' => 'Olá']]);
    line('*', '*', 'Save changes.')->update(['text' => ['pt_BR' => 'Gravar.']]);
    line('demo', 'actions', 'delete')->update(['text' => ['pt_BR' => 'Excluir', 'en' => '']]);
    line('demo', 'pages/dashboard', 'title')->update(['text' => ['pt_BR' => 'Painel']]);

    // a fresh translator, like the next request
    app()->forgetInstance('translator');

    expect(__('messages.welcome'))->toBe('Olá')
        ->and(__('messages.nav.home'))->toBe('Início')
        ->and(__('Save changes.'))->toBe('Gravar.')
        ->and(__('demo::actions.delete'))->toBe('Excluir')
        ->and(__('demo::pages/dashboard.title'))->toBe('Painel')
        ->and(trans('demo::actions.delete', [], 'en'))->toBe('Delete')
        ->and(line('*', 'messages', 'welcome')->value('pt_BR'))->toBe('Olá')
        ->and(line('demo', 'actions', 'delete')->value('en'))->toBe('Delete');
});

it('falls back to the files when the table does not exist', function () {
    Schema::drop('translation_lines');
    app()->forgetInstance('translator');
    app()->setLocale('pt_BR');

    expect(__('messages.welcome'))->toBe('Bem-vindo');
});

it('exports overrides into app, JSON and lang/vendor files', function () {
    app(TranslationManager::class)->scan();
    line('*', 'messages', 'nav.about')->update(['text' => ['pt_BR' => 'Sobre']]);
    TranslationLine::query()->create(['group' => '*', 'key' => 'Hello', 'text' => ['pt_BR' => 'Olá']]);
    line('demo', 'actions', 'delete')->update(['text' => ['pt_BR' => 'Excluir']]);

    $this->artisan('translation-manager:export', ['--clear' => true])->assertSuccessful();

    expect(File::getRequire($this->langPath.'/pt_BR/messages.php'))->toBe(['welcome' => 'Bem-vindo', 'nav' => ['home' => 'Início', 'about' => 'Sobre']])
        ->and(json_decode(File::get($this->langPath.'/pt_BR.json'), true))->toBe(['Save changes.' => 'Salvar alterações.', 'Hello' => 'Olá'])
        ->and(File::getRequire($this->langPath.'/vendor/demo/pt_BR/actions.php'))->toBe(['delete' => 'Excluir'])
        ->and(TranslationLine::query()->whereNotNull('text')->count())->toBe(0);

    app()->forgetInstance('translator');
    app()->setLocale('pt_BR');

    expect(__('demo::actions.delete'))->toBe('Excluir');
});

it('exports only the requested locales', function () {
    TranslationLine::query()->create(['group' => 'messages', 'key' => 'bye', 'text' => ['pt_BR' => 'Tchau', 'es' => 'Adiós']]);

    $this->artisan('translation-manager:export', ['--locale' => ['es'], '--clear' => true])->assertSuccessful();

    expect(File::exists($this->langPath.'/es/messages.php'))->toBeTrue()
        ->and(File::getRequire($this->langPath.'/pt_BR/messages.php'))->not->toHaveKey('bye')
        ->and(line('*', 'messages', 'bye')->text)->toBe(['pt_BR' => 'Tchau']);
});
