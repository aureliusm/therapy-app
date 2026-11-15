<?php

namespace Tests\Feature\Pages;

use App\Jobs\DownloadAppUpdate;
use App\Models\Setting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_api_key_stores_it_encrypted(): void
    {
        config(['therapy.gemini.api_key' => null]);

        Livewire::test('pages::settings')
            ->set('gemini_api_key', '  nov-kljuc  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('gemini_api_key', '')
            ->assertSet('hasGeminiApiKey', true);

        $this->assertSame('nov-kljuc', Setting::geminiApiKey());
        $this->assertNotSame('nov-kljuc', DB::table('settings')->value('gemini_api_key'));
    }

    public function test_saving_with_empty_api_key_keeps_existing_key(): void
    {
        Setting::current()->update(['gemini_api_key' => 'obstojeci-kljuc']);

        Livewire::test('pages::settings')
            ->set('base_prompt', 'Nov prompt')
            ->call('save');

        $this->assertSame('obstojeci-kljuc', Setting::geminiApiKey());
        $this->assertSame('Nov prompt', Setting::current()->base_prompt);
    }

    public function test_install_update_queues_background_download(): void
    {
        config([
            'therapy.updater.manifest_url' => 'https://updates.test/update.json',
            'nativephp.version' => '1.0.5',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://updates.test/update.json' => Http::response([
                'version' => '1.0.6',
                'dmg_url' => 'https://updates.test/app.dmg',
            ]),
        ]);
        Queue::fake([DownloadAppUpdate::class]);

        Livewire::test('pages::settings')->call('installUpdate');

        Queue::assertPushed(DownloadAppUpdate::class, fn (DownloadAppUpdate $job): bool => $job->latest['version'] === '1.0.6');
    }
}
