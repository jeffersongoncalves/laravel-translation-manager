<?php

namespace JeffersonGoncalves\TranslationManager\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\TranslationManager\TranslationManager;

class ExportCommand extends Command
{
    protected $signature = 'translation-manager:export
        {--locale=* : only these locales (default: all)}
        {--clear : remove the exported overrides from the database}';

    protected $description = 'Write the database overrides into lang/ (app, JSON and lang/vendor files).';

    public function handle(TranslationManager $manager): int
    {
        $files = $manager->export(array_values(array_map('strval', (array) $this->option('locale'))), (bool) $this->option('clear'));

        if ($files === []) {
            $this->components->info('No overrides to export.');

            return self::SUCCESS;
        }

        $this->components->info(count($files).' files written:');
        $this->components->bulletList(array_map(fn (string $file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file), $files));

        return self::SUCCESS;
    }
}
