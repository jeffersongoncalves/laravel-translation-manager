<?php

namespace JeffersonGoncalves\TranslationManager\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\TranslationManager\TranslationManager;

class ScanCommand extends Command
{
    protected $signature = 'translation-manager:scan';

    protected $description = 'Read the app, JSON and package lang files into the translation_lines table.';

    public function handle(TranslationManager $manager): int
    {
        $count = $manager->scan();

        $this->components->info("{$count} lines scanned for ".implode(', ', $manager->locales()).'.');

        return self::SUCCESS;
    }
}
