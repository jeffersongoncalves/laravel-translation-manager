<?php

namespace JeffersonGoncalves\TranslationManager;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

/**
 * Wraps the framework's file loader and lays the database overrides over whatever the files return,
 * so app, JSON and vendor (`package::group.key`) lines can all be overridden.
 */
class DatabaseLoader implements Loader
{
    public function __construct(private Loader $files, private TranslationManager $manager) {}

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<array-key, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);
        $namespace ??= '*';

        foreach ($this->manager->overrides($namespace, $group)[$locale] ?? [] as $key => $value) {
            if ($group === '*') {
                $lines[$key] = $value; // JSON keys are whole sentences: dots are not nesting
            } else {
                Arr::set($lines, $key, $value);
            }
        }

        return $lines;
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     */
    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    /**
     * @param  string  $path
     */
    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces()
    {
        return $this->files->namespaces();
    }

    /** The wrapped file loader: what the lang files say, without the overrides. */
    public function files(): Loader
    {
        return $this->files;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->files->{$method}(...$arguments);
    }
}
