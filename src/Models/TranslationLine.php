<?php

namespace JeffersonGoncalves\TranslationManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\TranslationManager\TranslationManager;

/**
 * One translation key. `namespace` is `*` for the app's own lines and `group` is `*` for JSON lines.
 *
 * @property int $id
 * @property string $namespace
 * @property string $group
 * @property string $key
 * @property array<string, string>|null $source values read from the lang files, per locale
 * @property array<string, string|null>|null $text database overrides, per locale
 */
class TranslationLine extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'namespace' => '*',
    ];

    protected $casts = [
        'source' => 'array',
        'text' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('translation-manager.table', 'translation_lines');
    }

    protected static function booted(): void
    {
        $flush = fn (self $line) => app(TranslationManager::class)->flush($line->namespace, $line->group);

        static::saved($flush);
        static::deleted($flush);
    }

    /** The string `__()` resolves to: the override when there is one, otherwise the file value. */
    public function value(string $locale): ?string
    {
        $override = $this->text[$locale] ?? null;

        return filled($override) ? $override : ($this->source[$locale] ?? null);
    }

    /** `filament-panels::pages.dashboard.title`, `validation.required`, or the JSON key itself. */
    public function fullKey(): string
    {
        $key = $this->group === '*' ? $this->key : "{$this->group}.{$this->key}";

        return $this->namespace === '*' ? $key : "{$this->namespace}::{$key}";
    }

    /**
     * Lines with neither a file value nor an override for the locale.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMissing(Builder $query, string $locale): void
    {
        foreach (['source', 'text'] as $column) {
            $query->where(fn (Builder $q) => $q->whereNull("{$column}->{$locale}")->orWhere("{$column}->{$locale}", ''));
        }
    }
}
