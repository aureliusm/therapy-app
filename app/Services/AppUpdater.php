<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Native\Desktop\Facades\App as NativeApp;
use Native\Desktop\Facades\Shell;
use RuntimeException;

class AppUpdater
{
    /**
     * @return array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}|null
     */
    public function latest(): ?array
    {
        $manifestUrl = config('therapy.updater.manifest_url');

        if (filled($manifestUrl)) {
            return $this->fromManifest((string) $manifestUrl);
        }

        $owner = config('therapy.updater.github_owner');
        $repo = config('therapy.updater.github_repo');

        if (filled($owner) && filled($repo)) {
            return $this->fromGitHub((string) $owner, (string) $repo);
        }

        return null;
    }

    public function currentVersion(): string
    {
        return (string) config('nativephp.version', '1.0.0');
    }

    public function isNewer(?array $latest): bool
    {
        if (! $latest || blank($latest['version'] ?? null)) {
            return false;
        }

        return version_compare($this->normalizeVersion($latest['version']), $this->normalizeVersion($this->currentVersion()), '>');
    }

    /**
     * @return array{available: bool, current: string, latest: ?array, message: string}
     */
    public function status(): array
    {
        try {
            $latest = $this->latest();
        } catch (RuntimeException $e) {
            return [
                'available' => false,
                'current' => $this->currentVersion(),
                'latest' => null,
                'message' => $e->getMessage(),
            ];
        }

        if ($latest === null) {
            return [
                'available' => false,
                'current' => $this->currentVersion(),
                'latest' => null,
                'message' => 'Updater še ni nastavljen. V .env dodaj UPDATE_MANIFEST_URL ali GITHUB_OWNER/GITHUB_REPO.',
            ];
        }

        if (! $this->isNewer($latest)) {
            return [
                'available' => false,
                'current' => $this->currentVersion(),
                'latest' => $latest,
                'message' => 'Imaš najnovejšo različico ('.$this->currentVersion().').',
            ];
        }

        return [
            'available' => true,
            'current' => $this->currentVersion(),
            'latest' => $latest,
            'message' => 'Na voljo je različica '.$latest['version'].'.',
        ];
    }

    /**
     * @param  array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}  $latest
     */
    public function install(array $latest): string
    {
        $dmgUrl = $latest['dmg_url'] ?? null;

        if (blank($dmgUrl)) {
            if (filled($latest['html_url'] ?? null)) {
                $this->openUrl((string) $latest['html_url']);

                return 'Odprl sem stran s prenosom.';
            }

            throw new RuntimeException('Manjka povezava do DMG datoteke.');
        }

        $target = $this->downloadPath((string) $latest['version']);

        try {
            $response = Http::timeout(600)
                ->withOptions(['sink' => $target, 'allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Sebi-naproti-updater'])
                ->get($dmgUrl);

            if ($response->failed() || ! is_file($target) || filesize($target) < 1_000_000) {
                throw new RuntimeException('Prenos posodobitve ni uspel.');
            }
        } catch (\Throwable $e) {
            if (filled($latest['html_url'] ?? null)) {
                $this->openUrl((string) $latest['html_url']);

                return 'Samodejni prenos ni uspel, zato sem odprl stran z releaseom. Prenesi DMG tam in namesti.';
            }

            throw $e instanceof RuntimeException ? $e : new RuntimeException('Prenos posodobitve ni uspel.');
        }

        $this->openPath($target);

        return 'Posodobitev je prenesena. Namesti novo različico iz odprtega namestitvenega okna, nato zapri staro aplikacijo.';
    }

    /**
     * @return array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}
     */
    protected function fromManifest(string $url): array
    {
        $response = Http::timeout(20)->acceptJson()->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Manifest posodobitev ni dosegljiv.');
        }

        $json = $response->json();
        $arch = php_uname('m') === 'x86_64' ? 'x64' : 'arm64';
        $dmg = data_get($json, "mac.{$arch}")
            ?: data_get($json, 'mac.arm64')
            ?: data_get($json, 'dmg_url')
            ?: data_get($json, 'url');

        return [
            'version' => (string) ($json['version'] ?? ''),
            'notes' => isset($json['notes']) ? (string) $json['notes'] : null,
            'dmg_url' => $dmg ? (string) $dmg : null,
            'html_url' => isset($json['html_url']) ? (string) $json['html_url'] : null,
        ];
    }

    /**
     * @return array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}
     */
    protected function fromGitHub(string $owner, string $repo): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Sebi-naproti-updater',
        ];

        if (filled(config('therapy.updater.github_token'))) {
            $headers['Authorization'] = 'Bearer '.config('therapy.updater.github_token');
        }

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");

        if ($response->failed()) {
            throw new RuntimeException('GitHub release ni dosegljiv.');
        }

        $json = $response->json();
        $assets = collect($json['assets'] ?? []);
        $arch = php_uname('m') === 'x86_64' ? 'x64' : 'arm64';
        $dmg = $assets->first(fn ($asset) => str_ends_with(strtolower($asset['name'] ?? ''), '-'.$arch.'.dmg'))
            ?? $assets->first(fn ($asset) => str_ends_with(strtolower($asset['name'] ?? ''), '.dmg'));

        return [
            'version' => ltrim((string) ($json['tag_name'] ?? ''), 'v'),
            'notes' => isset($json['body']) ? (string) $json['body'] : null,
            'dmg_url' => isset($dmg['browser_download_url']) ? (string) $dmg['browser_download_url'] : null,
            'html_url' => isset($json['html_url']) ? (string) $json['html_url'] : null,
        ];
    }

    protected function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'v');
    }

    protected function downloadPath(string $version): string
    {
        $configured = rtrim((string) config('therapy.updater.download_dir'), '/');
        $dir = ($configured !== '' && is_dir($configured))
            ? $configured
            : $this->homeDirectory().'/Downloads';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.'/Sebi-naproti-'.$this->normalizeVersion($version).'.dmg';
    }

    protected function homeDirectory(): string
    {
        foreach ([
            getenv('HOME') ?: null,
            $_ENV['HOME'] ?? null,
            $_SERVER['HOME'] ?? null,
        ] as $home) {
            if (is_string($home) && $home !== '' && is_dir($home)) {
                return $home;
            }
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $dir = posix_getpwuid(posix_geteuid())['dir'] ?? null;
            if (is_string($dir) && $dir !== '' && is_dir($dir)) {
                return $dir;
            }
        }

        return storage_path('app/updates');
    }

    protected function openPath(string $path): void
    {
        if ($this->isNative()) {
            Shell::openFile($path);

            return;
        }

        Process::run(['open', $path]);
    }

    protected function openUrl(string $url): void
    {
        if ($this->isNative()) {
            Shell::openExternal($url);

            return;
        }

        Process::run(['open', $url]);
    }

    protected function isNative(): bool
    {
        try {
            return NativeApp::isRunningBundled() || filled(env('NATIVEPHP_RUNNING'));
        } catch (\Throwable) {
            return filled(env('NATIVEPHP_RUNNING'));
        }
    }
}
