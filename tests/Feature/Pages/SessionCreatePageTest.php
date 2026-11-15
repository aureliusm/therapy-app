<?php

namespace Tests\Feature\Pages;

use App\Jobs\ProcessTherapySession;
use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SessionCreatePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_text_note_creates_processing_session_and_dispatches_ai_job(): void
    {
        $client = Client::factory()->create();
        Queue::fake([ProcessTherapySession::class]);

        Livewire::test('pages::sessions.create', ['client' => $client])
            ->set('transcript', 'Danes sva govorila o mejah.')
            ->call('save')
            ->assertHasNoErrors();

        $session = TherapySession::query()->sole();
        $this->assertSame('processing', $session->status);
        $this->assertSame('Danes sva govorila o mejah.', $session->transcript);
        Queue::assertPushed(ProcessTherapySession::class, fn (ProcessTherapySession $job): bool => $job->sessionId === $session->id);
    }

    public function test_saving_without_ai_creates_draft_and_does_not_dispatch_job(): void
    {
        $client = Client::factory()->create();
        Queue::fake([ProcessTherapySession::class]);

        Livewire::test('pages::sessions.create', ['client' => $client])
            ->set('transcript', 'Kratek zapisek.')
            ->set('runAi', false)
            ->call('save');

        $this->assertSame('draft', TherapySession::query()->sole()->status);
        Queue::assertNotPushed(ProcessTherapySession::class);
    }

    public function test_rejects_session_without_transcript_or_audio(): void
    {
        $client = Client::factory()->create();

        Livewire::test('pages::sessions.create', ['client' => $client])
            ->call('save')
            ->assertHasErrors('transcript');

        $this->assertDatabaseEmpty('therapy_sessions');
    }
}
