<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class PrepareUpdateCommand extends Command
{
    protected $signature = 'app:prepare-update
        {--build : Najprej zgradi Mac arm64 paket}
        {--notes= : Opombe k različici}
        {--set-version= : Prepiši verzijo v config/nativephp.php}';

    protected $description = 'Pripravi update.json in kopira DMG v mapo releases/';

    public function handle(): int
    {
        if ($version = $this->option('set-version')) {
            $version = ltrim((string) $version, 'v');
            $this->bumpVersion($version);
            $this->applyVersion($version);
            $this->info('Verzija nastavljena na '.$version);
        }

        if ($this->option('build')) {
            $this->info('Gradim Mac arm64 …');
            $exit = $this->call('native:build', [
                'os' => 'mac',
                'arch' => 'arm64',
                '--no-interaction' => true,
            ]);

            if ($exit !== self::SUCCESS) {
                return $exit;
            }

            // native:build odstrani dev odvisnosti iz vendor/
            $this->info('Obnavljam composer odvisnosti …');
            Process::path(base_path())->timeout(300)->run(['composer', 'install', '--no-interaction']);
        }

        $version = (string) config('nativephp.version');
        $dist = base_path('nativephp/electron/dist');
        $dmgName = config('app.name').'-'.$version.'-arm64.dmg';
        $dmgPath = $dist.'/'.$dmgName;

        if (! is_file($dmgPath)) {
            $this->error('DMG ne obstaja: '.$dmgPath);
            $this->line('Najprej zaženi: php artisan native:build mac arm64');

            return self::FAILURE;
        }

        $releases = base_path('releases');
        File::ensureDirectoryExists($releases);
        File::copy($dmgPath, $releases.'/'.$dmgName);

        $publicBase = rtrim((string) config('therapy.updater.public_base_url'), '/');
        $dmgUrl = $publicBase !== ''
            ? $publicBase.'/'.rawurlencode($dmgName)
            : null;

        $manifest = [
            'version' => $version,
            'notes' => $this->option('notes') ?: 'Posodobitev aplikacije Sebi naproti.',
            'published_at' => now()->toIso8601String(),
            'mac' => [
                'arm64' => $dmgUrl,
            ],
            'dmg_url' => $dmgUrl,
        ];

        File::put(
            $releases.'/update.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        $this->info('Pripravljeno v releases/');
        $this->line('- '.$dmgName);
        $this->line('- update.json');
        $this->newLine();
        $this->line('Objavi na GitHub:');
        $this->line('  gh release create v'.$version.' "releases/'.$dmgName.'" --title "v'.$version.'" --notes '.escapeshellarg((string) ($this->option('notes') ?: 'Posodobitev')));

        return self::SUCCESS;
    }

    protected function applyVersion(string $version): void
    {
        $version = ltrim($version, 'v');
        config(['nativephp.version' => $version]);
        putenv('NATIVEPHP_APP_VERSION='.$version);
        $_ENV['NATIVEPHP_APP_VERSION'] = $version;
        $_SERVER['NATIVEPHP_APP_VERSION'] = $version;
    }

    protected function bumpVersion(string $version): void
    {
        $version = ltrim($version, 'v');
        $path = config_path('nativephp.php');
        $contents = File::get($path);
        $contents = preg_replace(
            "/('version'\\s*=>\\s*env\\('NATIVEPHP_APP_VERSION',\\s*')([^']*)('\\))/",
            '${1}'.$version.'${3}',
            $contents,
            1
        );

        File::put($path, $contents);

        $env = base_path('.env');
        if (is_file($env)) {
            $envContents = File::get($env);
            if (str_contains($envContents, 'NATIVEPHP_APP_VERSION=')) {
                $envContents = preg_replace('/^NATIVEPHP_APP_VERSION=.*$/m', 'NATIVEPHP_APP_VERSION='.$version, $envContents);
            } else {
                $envContents = rtrim($envContents)."\nNATIVEPHP_APP_VERSION=".$version."\n";
            }
            File::put($env, $envContents);
        }
    }
}
