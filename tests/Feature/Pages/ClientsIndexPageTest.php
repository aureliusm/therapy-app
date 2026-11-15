<?php

namespace Tests\Feature\Pages;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsIndexPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleting_client_removes_sessions_and_their_audio_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sessions/narek.webm', 'audio');
        $client = Client::factory()->create();
        $session = TherapySession::factory()->for($client)->withAudio('sessions/narek.webm')->create();

        Livewire::test('pages::clients.index')->call('deleteClient', $client->id);

        $this->assertModelMissing($client);
        $this->assertModelMissing($session);
        Storage::disk('local')->assertMissing('sessions/narek.webm');
    }
}
