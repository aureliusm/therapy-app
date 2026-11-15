<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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
            $this->bumpVersion((string) $version);
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

        if ($dmgUrl === null) {
            $this->newLine();
            $this->warn('Nastavi UPDATE_PUBLIC_BASE_URL v .env (javni URL do mape z DMG), nato ponovno zaženi ta ukaz.');
            $this->warn('V aplikaciji nastavi UPDATE_MANIFEST_URL na javni URL do update.json.');
        } else {
            $this->newLine();
            $this->line('Manifest URL naj bo: '.$publicBase.'/update.json');
            $this->line('V .env: UPDATE_MANIFEST_URL='.$publicBase.'/update.json');
        }

        return self::SUCCESS;
    }

    protected function bumpVersion(string $version): void
    {
        $path = config_path('nativephp.php');
        $contents = File::get($path);
        $contents = preg_replace(
            "/('version'\\s*=>\\s*env\\('NATIVEPHP_APP_VERSION',\\s*')([^']*)('\\))/",
            '${1}'.ltrim($version, 'v').'${3}',
            $contents,
            1
        );

        File::put($path, $contents);

        $env = base_path('.env');
        if (is_file($env)) {
            $envContents = File::get($env);
            if (str_contains($envContents, 'NATIVEPHP_APP_VERSION=')) {
                $envContents = preg_replace('/^NATIVEPHP_APP_VERSION=.*$/m', 'NATIVEPHP_APP_VERSION='.ltrim($version, 'v'), $envContents);
            } else {
                $envContents = rtrim($envContents)."\nNATIVEPHP_APP_VERSION=".ltrim($version, 'v')."\n";
            }
            File::put($env, $envContents);
        }
    }
}
