<?php

use App\Jobs\ProcessTherapySession;
use App\Models\Client;
use App\Models\TherapySession;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public Client $client;

    public TherapySession $session;

    public function mount(Client $client, TherapySession $session): void
    {
        abort_unless($session->client_id === $client->id, 404);

        $this->client = $client;
        $this->session = $session;
    }

    public function refreshSession(): void
    {
        $this->session->refresh();
    }

    public function retryAi(): void
    {
        ProcessTherapySession::dispatch($this->session->id, retranscribe: blank($this->session->transcript) && $this->session->hasAudio());
        $this->session->update(['status' => 'processing', 'error_message' => null]);
        $this->session->refresh();
    }

    public function deleteSession(): void
    {
        $this->session->deleteAudio();
        $this->session->delete();
        $this->redirect(route('clients.show', $this->client), navigate: true);
    }

    public function title(): string
    {
        return 'Srečanje '.$this->session->session_date?->format('d.m.Y');
    }
};
?>

<div wire:poll.3s="refreshSession">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('clients.show', $client) }}" class="btn-ghost -ml-3" wire:navigate>← {{ $client->name }}</a>
            <p class="mt-4 font-script text-brand-light">ogled srečanja</p>
            <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ $session->session_date?->format('d. m. Y') }}</h2>
            <p class="mt-2 text-sm text-mauve-dark">{{ $session->therapy_type }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sessions.edit', [$client, $session]) }}" class="btn-primary" wire:navigate>Uredi</a>
            @if ($session->hasFailed() || $session->status === 'draft')
                <button type="button" wire:click="retryAi" class="btn-secondary">Ponovno obdelaj z AI</button>
            @endif
            <button type="button" wire:click="deleteSession" wire:confirm="Res izbrišem to srečanje?" class="btn-ghost text-brand-light">Izbriši</button>
        </div>
    </div>

    @if ($session->isProcessing())
        <div class="app-card mb-6 border-brand/20 bg-brand/5">
            <p class="font-medium text-mauve-dark">AI pripravlja povzetek …</p>
            <p class="mt-1 text-sm text-muted">Stran se osvežuje sama. Lahko počakaš tukaj.</p>
        </div>
    @endif

    @if ($session->hasFailed())
        <div class="app-card mb-6 border-red-200 bg-red-50">
            <p class="font-medium text-red-700">Obdelava ni uspela</p>
            <p class="mt-1 text-sm text-red-600/90">{{ $session->error_message }}</p>
        </div>
    @endif

    <div class="space-y-5">
        <section class="app-card space-y-3">
            <p class="font-script text-brand">povzetek</p>
            <div class="whitespace-pre-wrap text-[15px] leading-7 text-ink/90">{{ $session->summary ?: 'Povzetek še ni na voljo.' }}</div>
        </section>

        @if (! empty($session->topics))
            <section class="app-card space-y-3">
                <p class="font-script text-brand">ključne teme</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($session->topics as $topic)
                        <span class="chip">{{ $topic }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="app-card space-y-3">
                <p class="font-script text-brand">intervence</p>
                <div class="whitespace-pre-wrap text-sm leading-6 text-ink/85">{{ $session->interventions ?: '—' }}</div>
            </section>
            <section class="app-card space-y-3">
                <p class="font-script text-brand">opozorila in opažanja</p>
                <div class="whitespace-pre-wrap text-sm leading-6 text-ink/85">{{ $session->warnings ?: '—' }}</div>
            </section>
        </div>

        <section class="app-card space-y-3">
            <p class="font-script text-brand">za naslednje srečanje</p>
            <div class="whitespace-pre-wrap text-sm leading-6 text-ink/85">{{ $session->next_ideas ?: '—' }}</div>
        </section>

        @if ($session->hasAudio())
            <section class="app-card space-y-3">
                <p class="font-script text-brand">posnetek</p>
                <audio controls class="w-full" src="{{ route('sessions.audio', [$client, $session]) }}"></audio>
            </section>
        @endif

        <details class="app-card group">
            <summary class="cursor-pointer list-none font-script text-brand">
                transkript
                <span class="ml-2 text-xs font-sans text-muted group-open:hidden">prikaži</span>
            </summary>
            <div class="mt-4 whitespace-pre-wrap border-t border-brand/10 pt-4 text-sm leading-6 text-ink/80">
                {{ $session->transcript ?: 'Transkript še ni na voljo.' }}
            </div>
        </details>
    </div>
</div>
