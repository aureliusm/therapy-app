<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Native\Desktop\Drivers\Electron\ElectronServiceProvider;

class InstallElectronPluginsCommand extends Command
{
    protected const IMPORT_MARKER = '// app-electron-plugins';

    protected $signature = 'app:install-electron-plugins
        {--electron-path= : Pot do Electron projekta (privzeto NativePHP)}';

    protected $description = 'Vključi pluginе iz resources/electron/plugins v glavni proces Electrona';

    public function handle(): int
    {
        $electronPath = rtrim((string) ($this->option('electron-path') ?: ElectronServiceProvider::electronPath()), '/');
        $mainPath = $electronPath.'/src/main/index.js';

        if (! is_file($mainPath)) {
            $this->error('Glavni proces Electrona ne obstaja: '.$mainPath);

            return self::FAILURE;
        }

        $plugins = File::glob(resource_path('electron/plugins/*.js'));
        $targetDirectory = dirname($mainPath).'/app-plugins';

        File::deleteDirectory($targetDirectory);
        File::ensureDirectoryExists($targetDirectory);

        foreach ($plugins as $plugin) {
            File::copy($plugin, $targetDirectory.'/'.basename($plugin));
        }

        $imports = collect($plugins)
            ->map(fn (string $plugin): string => "import './app-plugins/".basename($plugin)."'; ".self::IMPORT_MARKER)
            ->implode("\n");

        $main = collect(preg_split('/\r\n|\r|\n/', File::get($mainPath)) ?: [])
            ->reject(fn (string $line): bool => str_contains($line, self::IMPORT_MARKER))
            ->implode("\n");

        File::put($mainPath, ($imports !== '' ? $imports."\n" : '').$main);

        $this->info('Vključeni plugini: '.count($plugins));

        return self::SUCCESS;
    }
}
