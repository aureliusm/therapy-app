<?php

use App\Jobs\ProcessTherapySession;
use App\Models\Client;
use App\Models\Setting;
use App\Models\TherapySession;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component
{
    use WithFileUploads;

    public Client $client;

    public string $session_date = '';

    public string $therapy_type = '';

    public string $transcript = '';

    public $audio;

    public bool $runAi = true;

    public function mount(Client $client): void
    {
        $this->client = $client;
        $this->session_date = now()->toDateString();
        $this->therapy_type = $client->resolvedTherapyType(Setting::current()->default_therapy_type);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'session_date' => ['required', 'date'],
            'therapy_type' => ['required', 'string', 'max:255'],
            'transcript' => ['nullable', 'string'],
            'audio' => ['nullable', 'file', 'max:51200', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/mp3,video/webm'],
            'runAi' => ['boolean'],
        ]);

        if (blank($validated['transcript']) && ! $this->audio) {
            $this->addError('transcript', 'Dodaj narek ali besedilo zapiska.');

            return;
        }

        $audioPath = null;
        $audioMime = null;

        if ($this->audio) {
            $audioPath = $this->audio->store('sessions', 'local');
            $audioMime = $this->audio->getMimeType();
        }

        $shouldProcess = $this->runAi && (filled($validated['transcript']) || filled($audioPath));

        $session = TherapySession::query()->create([
            'client_id' => $this->client->id,
            'session_date' => $validated['session_date'],
            'therapy_type' => $validated['therapy_type'],
            'transcript' => $validated['transcript'] ?: null,
            'audio_path' => $audioPath,
            'audio_mime' => $audioMime,
            'status' => $shouldProcess ? 'processing' : 'draft',
        ]);

        if ($shouldProcess) {
            ProcessTherapySession::dispatch($session->id);
        }

        $this->redirect(route('sessions.show', [$this->client, $session]), navigate: true);
    }

    public function title(): string
    {
        return 'Novo srečanje';
    }
};
?>

<div>
    <div class="mb-8">
        <a href="{{ route('clients.show', $client) }}" class="btn-ghost -ml-3" wire:navigate>← {{ $client->name }}</a>
        <p class="mt-4 font-script text-brand-light">novo srečanje</p>
        <h2 class="text-3xl font-semibold tracking-tight text-ink">Dodaj srečanje</h2>
        <p class="mt-2 text-sm text-muted">Povej zapisek po seansi ali ga vpiši. AI nato pripravi povzetek.</p>
    </div>

    <form wire:submit="save" class="space-y-6" x-data="dictationUploader()">
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

        <div class="app-card space-y-6 text-center">
            <div>
                <p class="font-script text-brand">narek</p>
                <h3 class="mt-1 text-xl font-semibold tracking-tight text-ink">Povej zapisek</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted">Kratek govorjeni povzetek po srečanju. Primerno za nekaj minut, ne za celotno seanso.</p>
            </div>

            <div class="flex flex-col items-center gap-4">
                <button type="button"
                        @click="toggle()"
                        class="flex h-28 w-28 items-center justify-center rounded-full text-white shadow-lg transition"
                        :class="recording ? 'bg-brand-light animate-pulse' : 'bg-brand hover:bg-brand-hover'">
                    <span class="text-sm font-medium" x-text="recording ? 'Ustavi' : 'Snemaj'"></span>
                </button>

                <div x-show="recording" class="h-1.5 w-40 overflow-hidden rounded-full bg-brand/10">
                    <div class="h-full rounded-full bg-brand transition-[width] duration-150" :style="`width: ${Math.min(100, level * 400)}%`"></div>
                </div>

                <p class="text-sm" :class="hasMicrophoneProblem ? 'max-w-md text-brand-light' : 'text-muted'" x-text="statusText"></p>

                <template x-if="previewUrl">
                    <audio controls class="w-full max-w-md" :src="previewUrl"></audio>
                </template>

                <input type="file" class="hidden" x-ref="fileInput" wire:model="audio" accept="audio/*,video/webm">
                @error('audio') <p class="text-sm text-brand-light">{{ $message }}</p> @enderror
                <div wire:loading wire:target="audio" class="text-sm text-mauve-dark">Nalagam posnetek …</div>
            </div>
        </div>

        <div class="app-card space-y-3">
            <div>
                <p class="font-script text-brand">ali z besedilom</p>
                <label class="field-label mt-2" for="transcript">Zapisek / transkript</label>
                <textarea id="transcript" wire:model="transcript" class="field-textarea min-h-40" placeholder="Kaj se je zgodilo na srečanju, občutki, intervence …"></textarea>
                @error('transcript') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-3 text-sm text-ink/80">
                <input type="checkbox" wire:model.live="runAi" class="size-4 rounded border-brand/30 text-brand focus:ring-brand/30">
                Samodejno naredi povzetek z AI
            </label>

            @if ($runAi && ! \App\Models\Setting::geminiApiKey())
                <p class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Gemini API ključ še ni nastavljen. Zapisek lahko shraniš, AI povzetek pa bo deloval šele, ko ključ vneseš v <a href="{{ route('settings') }}" class="underline" wire:navigate>Nastavitvah</a>.
                </p>
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Shrani srečanje</span>
                <span wire:loading wire:target="save">Shranjujem …</span>
            </button>
            <a href="{{ route('clients.show', $client) }}" class="btn-secondary" wire:navigate>Prekliči</a>
        </div>
    </form>
</div>

@script
<script>
Alpine.data('dictationUploader', () => ({
    recording: false,
    mediaRecorder: null,
    chunks: [],
    previewUrl: null,
    statusText: 'Pripravljeno za narek',
    level: 0,
    peakLevel: 0,
    hasMicrophoneProblem: false,

    async toggle() {
        if (this.recording) {
            this.mediaRecorder?.stop();
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const stopMeter = this.startLevelMeter(stream);
            this.chunks = [];
            this.hasMicrophoneProblem = false;
            this.mediaRecorder = new MediaRecorder(stream);
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) this.chunks.push(event.data);
            };
            this.mediaRecorder.onstop = () => {
                stopMeter();
                stream.getTracks().forEach((track) => track.stop());
                const blob = new Blob(this.chunks, { type: this.mediaRecorder.mimeType || 'audio/webm' });
                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = URL.createObjectURL(blob);
                const file = new File([blob], 'narek-' + Date.now() + '.webm', { type: blob.type });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                this.$refs.fileInput.files = transfer.files;
                this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                // macOS aplikaciji brez dovoljenja za mikrofon ne zavrne snemanja, ampak vrača tišino.
                this.hasMicrophoneProblem = this.peakLevel < 0.0005;
                this.statusText = this.hasMicrophoneProblem
                    ? 'Posnetek je tih – mikrofon ne zajema zvoka. Dovoli dostop v Sistemskih nastavitvah → Zasebnost in varnost → Mikrofon, nato aplikacijo znova zaženi.'
                    : 'Posnetek pripravljen';
                this.recording = false;
            };
            this.mediaRecorder.start();
            this.recording = true;
            this.statusText = 'Snemam … klikni za ustavitev';
        } catch (error) {
            this.hasMicrophoneProblem = true;
            this.statusText = 'Mikrofon ni na voljo. Dovoli dostop v Sistemskih nastavitvah → Zasebnost in varnost → Mikrofon, nato aplikacijo znova zaženi.';
        }
    },

    startLevelMeter(stream) {
        const context = new AudioContext();
        const analyser = context.createAnalyser();
        analyser.fftSize = 1024;
        context.createMediaStreamSource(stream).connect(analyser);
        const samples = new Float32Array(analyser.fftSize);
        this.level = 0;
        this.peakLevel = 0;

        const interval = setInterval(() => {
            analyser.getFloatTimeDomainData(samples);
            this.level = Math.sqrt(samples.reduce((sum, sample) => sum + sample * sample, 0) / samples.length);
            this.peakLevel = Math.max(this.peakLevel, this.level);
        }, 150);

        return () => {
            clearInterval(interval);
            context.close();
            this.level = 0;
        };
    },
}));
</script>
@endscript
