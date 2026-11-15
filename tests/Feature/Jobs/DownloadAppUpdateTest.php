<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DownloadAppUpdate;
use App\Services\AppUpdater;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DownloadAppUpdateTest extends TestCase
{
    public function test_successful_download_stores_done_state_and_opens_dmg(): void
    {
        $downloadDir = $this->temporaryDownloadDirectory();
        config(['therapy.updater.download_dir' => $downloadDir]);
        Http::preventStrayRequests();
        Http::fake(['https://updates.test/app.dmg' => Http::response(str_repeat('x', 1_100_000))]);
        Process::fake();

        (new DownloadAppUpdate($this->latest()))->handle(app(AppUpdater::class));

        $state = Cache::get(DownloadAppUpdate::CACHE_KEY);
        $this->assertSame('done', $state['state']);
        $this->assertSame(100, $state['percent']);
        $this->assertFileExists($downloadDir.'/Sebi-naproti-1.0.6.dmg');
        Process::assertRan(fn ($process): bool => $process->command === ['open', $downloadDir.'/Sebi-naproti-1.0.6.dmg']);
    }

    public function test_failed_download_stores_failed_state_with_message(): void
    {
        config(['therapy.updater.download_dir' => $this->temporaryDownloadDirectory()]);
        Http::preventStrayRequests();
        Http::fake(['https://updates.test/app.dmg' => Http::response('Not found', 404)]);

        (new DownloadAppUpdate($this->latest()))->handle(app(AppUpdater::class));

        $state = Cache::get(DownloadAppUpdate::CACHE_KEY);
        $this->assertSame('failed', $state['state']);
        $this->assertSame('Prenos posodobitve ni uspel.', $state['message']);
    }

    /**
     * @return array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}
     */
    private function latest(): array
    {
        return ['version' => '1.0.6', 'notes' => null, 'dmg_url' => 'https://updates.test/app.dmg', 'html_url' => null];
    }

    private function temporaryDownloadDirectory(): string
    {
        $directory = sys_get_temp_dir().'/sebi-update-test-'.uniqid();
        File::ensureDirectoryExists($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        return $directory;
    }
}
