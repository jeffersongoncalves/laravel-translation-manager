<?php

namespace JeffersonGoncalves\TranslationManager;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JeffersonGoncalves\TranslationManager\Models\TranslationLine;
use Throwable;

class TranslationManager
{
    /** Keys past this length don't fit the unique index; such JSON "keys" are paragraphs anyway. */
    public const MAX_KEY_LENGTH = 500;

    /**
     * Overrides of one namespace + group, cached until a line of that group is saved.
     *
     * @return array<string, array<string, string>> locale => [key => value]
     */
    public function overrides(string $namespace, string $group): array
    {
        try {
            return $this->cache()->rememberForever($this->cacheKey($namespace, $group), function () use ($namespace, $group) {
                $overrides = [];

                TranslationLine::query()->where('namespace', $namespace)->where('group', $group)->where(fn (Builder $q) => $q->whereNotNull('text'))
                    ->each(function (TranslationLine $line) use (&$overrides) {
                        foreach ($line->text ?? [] as $locale => $value) {
                            if (filled($value)) {
                                $overrides[$locale][$line->key] = $value;
                            }
                        }
                    });

                return $overrides;
            });
        } catch (Throwable) {
            return []; // table not migrated yet, or no database at all: the files still work
        }
    }

    public function flush(string $namespace, string $group): void
    {
        $this->cache()->forget($this->cacheKey($namespace, $group));
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        $configured = (array) config('translation-manager.locales', []);
        if ($configured !== []) {
            return array_values(array_map('strval', $configured));
        }

        $found = [config('app.locale'), config('app.fallback_locale')];
        foreach (File::directories(lang_path()) as $dir) {
            if (basename($dir) !== 'vendor') {
                $found[] = basename($dir);
            }
        }
        foreach (File::glob(lang_path('*.json')) ?: [] as $file) {
            $found[] = pathinfo($file, PATHINFO_FILENAME);
        }

        $locales = array_values(array_unique(array_filter(array_map('strval', $found))));
        sort($locales);

        return $locales;
    }

    /**
     * Read every lang file (app, JSON and the registered package namespaces) for every locale and store
     * the values in `source`. Overrides in `text` are never touched.
     *
     * @return int lines stored
     */
    public function scan(): int
    {
        $files = $this->fileLoader();
        $locales = $this->locales();
        $lines = [];

        foreach ($this->groups($files) as [$namespace, $group]) {
            foreach ($locales as $locale) {
                $values = $files->load($locale, $group, $namespace);
                $values = $group === '*' ? $values : Arr::dot($values);

                foreach ($values as $key => $value) {
                    if (is_string($value) && mb_strlen((string) $key) <= self::MAX_KEY_LENGTH) {
                        $lines["{$namespace}\0{$group}\0{$key}"][$locale] = $value;
                    }
                }
            }
        }

        $now = now();
        $rows = [];
        foreach ($lines as $id => $source) {
            [$namespace, $group, $key] = explode("\0", $id, 3);
            $rows[] = ['namespace' => $namespace, 'group' => $group, 'key' => $key, 'source' => json_encode($source),
                'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            TranslationLine::query()->upsert($chunk, ['namespace', 'group', 'key'], ['source', 'updated_at']);
        }

        return count($rows);
    }

    /**
     * Write the overrides into the app's lang files: `lang/{locale}.json`, `lang/{locale}/{group}.php` and
     * `lang/vendor/{namespace}/{locale}/{group}.php` (the files Laravel already lays over a package's own).
     * Existing keys in those files are kept; `$clear` empties the overrides once they live in the files.
     *
     * @param  list<string>  $locales  empty = all
     * @return list<string> files written
     */
    public function export(array $locales = [], bool $clear = false): array
    {
        $files = [];

        foreach (TranslationLine::query()->where(fn (Builder $q) => $q->whereNotNull('text'))->get()->groupBy(fn (TranslationLine $l) => "{$l->namespace}\0{$l->group}") as $lines) {
            /** @var TranslationLine $first */
            $first = $lines->first();
            $byLocale = [];

            foreach ($lines as $line) {
                foreach ($line->text ?? [] as $locale => $value) {
                    if (filled($value) && ($locales === [] || in_array($locale, $locales, true))) {
                        $byLocale[$locale][$line->key] = $value;
                    }
                }
            }

            foreach ($byLocale as $locale => $values) {
                $files[] = $this->write($first->namespace, $first->group, $locale, $values);
            }

            if ($clear) {
                foreach ($lines as $line) {
                    $line->text = $locales === [] ? null : (array_diff_key($line->text ?? [], array_flip($locales)) ?: null);
                    $line->save();
                }
            }
        }

        return $files;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function write(string $namespace, string $group, string $locale, array $values): string
    {
        if ($group === '*') {
            $path = lang_path("{$locale}.json");
            $current = File::exists($path) ? (array) json_decode(File::get($path), true) : [];
            File::put($path, json_encode(array_replace($current, $values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            return $path;
        }

        $path = $namespace === '*' ? lang_path("{$locale}/{$group}.php") : lang_path("vendor/{$namespace}/{$locale}/{$group}.php");
        $current = File::exists($path) ? (array) File::getRequire($path) : [];
        foreach ($values as $key => $value) {
            Arr::set($current, $key, $value);
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\nreturn ".self::exportArray($current).";\n");

        return $path;
    }

    /**
     * Every [namespace, group] that has a file: app groups, the JSON group, and each package namespace's
     * groups (including the ones only published to lang/vendor).
     *
     * @return list<array{string, string}>
     */
    private function groups(Loader $files): array
    {
        $groups = [['*', '*']];

        foreach (self::groupsIn(lang_path()) as $group) {
            $groups[] = ['*', $group];
        }

        $include = (array) config('translation-manager.namespaces.include', []);
        $exclude = (array) config('translation-manager.namespaces.exclude', []);

        foreach ($files->namespaces() as $namespace => $hint) {
            if (in_array($namespace, $exclude, true) || ($include !== [] && ! in_array($namespace, $include, true))) {
                continue;
            }

            foreach ([...self::groupsIn($hint), ...self::groupsIn(lang_path("vendor/{$namespace}"))] as $group) {
                $groups[] = [$namespace, $group];
            }
        }

        return array_values(array_unique($groups, SORT_REGULAR));
    }

    /**
     * Group names under a lang root, across its locale folders: `messages`, and `pages/dashboard` for
     * nested files (Filament keeps its lines that way).
     *
     * @return list<string>
     */
    private static function groupsIn(string $root): array
    {
        if (! File::isDirectory($root)) {
            return [];
        }

        $groups = [];
        foreach (File::directories($root) as $localeDir) {
            if (basename($localeDir) === 'vendor') {
                continue;
            }

            foreach (File::allFiles($localeDir) as $file) {
                if ($file->getExtension() === 'php') {
                    $groups[] = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
                }
            }
        }

        return array_values(array_unique($groups));
    }

    private function fileLoader(): Loader
    {
        // Through the translator: packages register their namespaces when it resolves (loadTranslationsFrom).
        $loader = app('translator')->getLoader();

        return $loader instanceof DatabaseLoader ? $loader->files() : $loader;
    }

    private function cache(): Repository
    {
        return Cache::store(config('translation-manager.cache_store'));
    }

    private function cacheKey(string $namespace, string $group): string
    {
        return "translation-manager:{$namespace}:{$group}";
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private static function exportArray(array $array, int $depth = 1): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $lines = [];
        foreach ($array as $key => $value) {
            $lines[] = $indent.var_export($key, true).' => '.(is_array($value) ? self::exportArray($value, $depth + 1) : var_export($value, true)).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth - 1).']';
    }
}
