<?php

use App\Jobs\ProcessTherapySession;
use App\Models\Client;
use App\Models\TherapySession;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component
{
    use WithFileUploads;

    public Client $client;

    public TherapySession $session;

    public string $session_date = '';

    public string $therapy_type = '';

    public string $transcript = '';

    public string $summary = '';

    public string $interventions = '';

    public string $warnings = '';

    public string $next_ideas = '';

    public string $other_approaches = '';

    public $audio;

    public bool $removeAudio = false;

    public bool $rerunAi = false;

    public function mount(Client $client, TherapySession $session): void
    {
        abort_unless($session->client_id === $client->id, 404);

        $this->client = $client;
        $this->session = $session;
        $this->session_date = $session->session_date?->toDateString() ?? now()->toDateString();
        $this->therapy_type = $session->therapy_type;
        $this->transcript = (string) $session->transcript;
        $this->summary = (string) $session->summary;
        $this->interventions = (string) $session->interventions;
        $this->warnings = (string) $session->warnings;
        $this->next_ideas = (string) $session->next_ideas;
        $this->other_approaches = (string) $session->other_approaches;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'session_date' => ['required', 'date'],
            'therapy_type' => ['required', 'string', 'max:255'],
            'transcript' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'interventions' => ['nullable', 'string'],
            'warnings' => ['nullable', 'string'],
            'next_ideas' => ['nullable', 'string'],
            'other_approaches' => ['nullable', 'string'],
            'audio' => ['nullable', 'file', 'max:51200', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/mp3,video/webm'],
        ]);

        $existingDetails = $this->session->insight_details ?? [];

        if ($this->removeAudio) {
            $this->session->deleteAudio();
        }

        $hasNewAudio = (bool) $this->audio;

        if ($this->audio) {
            if ($this->session->audio_path) {
                $this->session->deleteAudio();
            }
            $this->session->audio_path = $this->audio->store('sessions', 'local');
            $this->session->audio_mime = $this->audio->getMimeType();
        }

        $this->session->fill([
            'session_date' => $validated['session_date'],
            'therapy_type' => $validated['therapy_type'],
            'transcript' => $validated['transcript'] ?: null,
            'summary' => $validated['summary'] ?: null,
            'topics' => null,
            'interventions' => $validated['interventions'] ?: null,
            'warnings' => $validated['warnings'] ?: null,
            'next_ideas' => $validated['next_ideas'] ?: null,
            'insight_details' => [
                'interventions' => TherapySession::alignInsights($validated['interventions'] ?? null, $existingDetails['interventions'] ?? []),
                'warnings' => TherapySession::alignInsights($validated['warnings'] ?? null, $existingDetails['warnings'] ?? []),
                'next_ideas' => TherapySession::alignInsights($validated['next_ideas'] ?? null, $existingDetails['next_ideas'] ?? []),
            ],
            'other_approaches' => $validated['other_approaches'] ?: null,
        ]);

        if ($this->rerunAi) {
            $this->session->status = 'processing';
            $this->session->error_message = null;
            $this->session->save();
            ProcessTherapySession::dispatch($this->session->id, retranscribe: $hasNewAudio);
        } else {
            if ($this->session->status === 'draft' && filled($this->session->summary)) {
                $this->session->status = 'ready';
            }
            $this->session->save();
        }

        $this->redirect(route('sessions.show', [$this->client, $this->session]), navigate: true);
    }

    public function title(): string
    {
        return 'Uredi srečanje';
    }
};
?>

<div>
    <div class="mb-8">
        <a href="{{ route('sessions.show', [$client, $session]) }}" class="btn-ghost -ml-3" wire:navigate>← Nazaj na ogled</a>
        <p class="mt-4 font-script text-brand-light">urejanje</p>
        <h2 class="text-3xl font-semibold tracking-tight text-ink">Uredi srečanje</h2>
    </div>

    <form wire:submit="save" class="space-y-5">
        <div class="app-card grid gap-5 sm:grid-cols-2">
            <div>
                <label class="field-label" for="session_date">Datum</label>
                <input id="session_date" type="date" wire:model="session_date" class="field-input">
                @error('session_date') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="therapy_type">Vrsta terapije</label>
                <select id="therapy_type" wire:model="therapy_type" class="field-input">
                    @foreach (config('therapy.therapy_types') as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <section class="app-card space-y-3">
            <p class="font-script text-brand">povzetek</p>
            <textarea wire:model="summary" class="field-textarea min-h-40"></textarea>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="app-card space-y-3">
                <p class="font-script text-brand">uporabljene intervence</p>
                <textarea wire:model="interventions" class="field-textarea"></textarea>
            </section>
            <section class="app-card space-y-3">
                <p class="font-script text-brand">opozorila in opažanja</p>
                <textarea wire:model="warnings" class="field-textarea"></textarea>
            </section>
        </div>

        <section class="app-card space-y-3">
            <p class="font-script text-brand">za naslednje srečanje</p>
            <textarea wire:model="next_ideas" class="field-textarea"></textarea>
        </section>

        <section class="app-card space-y-3">
            <p class="font-script text-brand">priporočila drugih smeri</p>
            <textarea wire:model="other_approaches" class="field-textarea"></textarea>
        </section>

        <section class="app-card space-y-3">
            <p class="font-script text-brand">transkript</p>
            <textarea wire:model="transcript" class="field-textarea min-h-40"></textarea>
        </section>

        <section class="app-card space-y-4">
            <p class="font-script text-brand">posnetek</p>
            @if ($session->hasAudio() && ! $removeAudio)
                <audio controls class="w-full" src="{{ route('sessions.audio', [$client, $session]) }}"></audio>
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" wire:model.live="removeAudio" class="size-4 rounded border-brand/30 text-brand focus:ring-brand/30">
                    Izbriši obstoječi posnetek
                </label>
            @endif
            <div>
                <label class="field-label" for="audio">Zamenjaj / dodaj posnetek</label>
                <input id="audio" type="file" wire:model="audio" accept="audio/*,video/webm" class="field-input">
                <div wire:loading wire:target="audio" class="mt-1 text-sm text-mauve-dark">Nalagam …</div>
                @error('audio') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" wire:model.live="rerunAi" class="size-4 rounded border-brand/30 text-brand focus:ring-brand/30">
                Po shranjevanju znova zaženi AI povzetek (nov posnetek se najprej prepiše)
            </label>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Shrani</span>
                <span wire:loading wire:target="save">Shranjujem …</span>
            </button>
            <a href="{{ route('sessions.show', [$client, $session]) }}" class="btn-secondary" wire:navigate>Prekliči</a>
        </div>
    </form>
</div>
