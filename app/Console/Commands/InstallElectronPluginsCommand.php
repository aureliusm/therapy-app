<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Native\Desktop\Drivers\Electron\ElectronServiceProvider;

class InstallElectronPluginsCommand extends Command
{
    protected const MARKER = '// app-electron-plugins';

    protected $signature = 'app:install-electron-plugins
        {--electron-path= : Pot do Electron projekta (privzeto NativePHP)}';

    protected $description = 'Vključi pluginе iz resources/electron/plugins v glavni proces Electrona in nastavi ad-hoc podpis za macOS';

    public function handle(): int
    {
        $electronPath = rtrim((string) ($this->option('electron-path') ?: ElectronServiceProvider::electronPath()), '/');
        $mainPath = $electronPath.'/src/main/index.js';
        $builderConfigPath = $electronPath.'/electron-builder.mjs';

        foreach ([$mainPath, $builderConfigPath] as $path) {
            if (! is_file($path)) {
                $this->error('Datoteka Electron projekta ne obstaja: '.$path);

                return self::FAILURE;
            }
        }

        $plugins = File::glob(resource_path('electron/plugins/*.js'));
        $targetDirectory = dirname($mainPath).'/app-plugins';

        File::deleteDirectory($targetDirectory);
        File::ensureDirectoryExists($targetDirectory);

        foreach ($plugins as $plugin) {
            File::copy($plugin, $targetDirectory.'/'.basename($plugin));
        }

        $imports = collect($plugins)
            ->map(fn (string $plugin): string => "import './app-plugins/".basename($plugin)."'; ".self::MARKER)
            ->implode("\n");

        $main = $this->withoutMarkedLines(File::get($mainPath));
        File::put($mainPath, ($imports !== '' ? $imports."\n" : '').$main);

        if (! $this->enableAdHocSigning($builderConfigPath)) {
            $this->error('V electron-builder.mjs ni sekcije "mac: {". Preveri, ali se je NativePHP spremenil.');

            return self::FAILURE;
        }

        $this->info('Vključeni plugini: '.count($plugins));

        return self::SUCCESS;
    }

    /**
     * macOS nepodpisani (samo "linker-signed") aplikaciji kljub dovoljenju vrača tišino z mikrofona.
     * Hardened runtime mora biti izklopljen, ker ad-hoc podpis nima Team ID za preverjanje knjižnic.
     */
    protected function enableAdHocSigning(string $builderConfigPath): bool
    {
        $config = $this->withoutMarkedLines(File::get($builderConfigPath));
        $patched = preg_replace(
            '/^(\s*)mac: \{$/m',
            "$0\n$1    identity: '-', ".self::MARKER."\n$1    hardenedRuntime: false, ".self::MARKER,
            $config,
            1,
            $count,
        );

        if ($count !== 1 || $patched === null) {
            return false;
        }

        File::put($builderConfigPath, $patched);

        return true;
    }

    protected function withoutMarkedLines(string $contents): string
    {
        return collect(preg_split('/\r\n|\r|\n/', $contents) ?: [])
            ->reject(fn (string $line): bool => str_contains($line, self::MARKER))
            ->implode("\n");
    }
}
