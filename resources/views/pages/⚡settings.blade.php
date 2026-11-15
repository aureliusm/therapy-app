<?php

use App\Jobs\DownloadAppUpdate;
use App\Models\Setting;
use App\Services\AppUpdater;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Nastavitve')] class extends Component
{
    public string $default_therapy_type = '';

    public string $base_prompt = '';

    public string $updateMessage = '';

    public bool $updateAvailable = false;

    public string $currentVersion = '';

    public string $latestVersion = '';

    public string $updateNotes = '';

    public bool $checkingUpdate = false;

    public string $gemini_api_key = '';

    public bool $hasGeminiApiKey = false;

    /** @var array{state?: string, percent?: int, message?: ?string} */
    public array $download = [];

    public function mount(AppUpdater $updater): void
    {
        $settings = Setting::current();
        $this->default_therapy_type = $settings->default_therapy_type;
        $this->base_prompt = (string) $settings->base_prompt;
        $this->hasGeminiApiKey = filled(Setting::geminiApiKey());
        $this->currentVersion = $updater->currentVersion();
        $this->refreshDownload();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'default_therapy_type' => ['required', 'string', 'max:255'],
            'base_prompt' => ['required', 'string'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (blank($validated['gemini_api_key'])) {
            unset($validated['gemini_api_key']);
        } else {
            $validated['gemini_api_key'] = trim($validated['gemini_api_key']);
        }

        Setting::current()->update($validated);
        $this->gemini_api_key = '';
        $this->hasGeminiApiKey = filled(Setting::geminiApiKey());
        session()->flash('status', 'Nastavitve so shranjene.');
    }

    public function isDownloading(): bool
    {
        return in_array($this->download['state'] ?? null, ['queued', 'downloading'], true);
    }

    public function refreshDownload(): void
    {
        $this->download = Cache::get(DownloadAppUpdate::CACHE_KEY, []);

        if (filled($this->download['message'] ?? null)) {
            $this->updateMessage = (string) $this->download['message'];
        }
    }

    public function checkForUpdates(AppUpdater $updater): void
    {
        $this->checkingUpdate = true;
        $status = $updater->status();
        $this->updateAvailable = $status['available'];
        $this->currentVersion = $status['current'];
        $this->latestVersion = (string) ($status['latest']['version'] ?? '');
        $this->updateNotes = (string) ($status['latest']['notes'] ?? '');
        $this->updateMessage = $status['message'];
        $this->checkingUpdate = false;
    }

    public function installUpdate(AppUpdater $updater): void
    {
        $status = $updater->status();

        if (! $status['available'] || ! $status['latest']) {
            $this->updateMessage = $status['message'];
            $this->updateAvailable = false;

            return;
        }

        if ($this->isDownloading()) {
            return;
        }

        DownloadAppUpdate::putState(['state' => 'queued']);
        DownloadAppUpdate::dispatch($status['latest']);
        $this->updateMessage = 'Prenašam različico '.$status['latest']['version'].' …';
        $this->refreshDownload();
    }
};
?>

<div>
    <div class="mb-8">
        <p class="font-script text-brand-light">osnovni prompt</p>
        <h2 class="text-3xl font-semibold tracking-tight text-ink">Nastavitve</h2>
        <p class="mt-2 max-w-2xl text-sm text-muted">Privzeta vrsta terapije in navodila, ki jih AI uporabi pri povzetkih.</p>
    </div>

    @if (session('status'))
        <div class="app-card mb-5 border-brand/20 bg-brand/5 text-sm text-mauve-dark">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="app-card mx-auto max-w-3xl space-y-5">
        <div>
            <label class="field-label" for="default_therapy_type">Privzeta vrsta terapije</label>
            <select id="default_therapy_type" wire:model="default_therapy_type" class="field-input">
                @foreach (config('therapy.therapy_types') as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label" for="base_prompt">Osnovni prompt</label>
            <textarea id="base_prompt" wire:model="base_prompt" class="field-textarea min-h-56"></textarea>
            <p class="mt-2 text-xs text-muted">Tukaj opiši ton pisanja, kaj šteje za intervenco in na kaj naj bo AI pozorna.</p>
            @error('base_prompt') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label" for="gemini_api_key">Gemini API ključ</label>
            <input id="gemini_api_key" type="password" wire:model="gemini_api_key" class="field-input" autocomplete="off"
                   placeholder="{{ $hasGeminiApiKey ? 'Ključ je nastavljen — vnesi novega, če ga želiš zamenjati' : 'Prilepi ključ iz Google AI Studio' }}">
            <p class="mt-2 text-xs text-muted">Ključ se shrani šifriran samo na tem računalniku.</p>
            @error('gemini_api_key') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary">Shrani nastavitve</button>
    </form>

    <section class="app-card mx-auto mt-6 max-w-3xl space-y-4">
        <div>
            <p class="font-script text-brand">posodobitve</p>
            <h3 class="mt-1 text-xl font-semibold tracking-tight text-ink">Posodobitve aplikacije</h3>
            <p class="mt-1 text-sm text-muted">Trenutna različica: <span class="font-medium text-ink">{{ $currentVersion }}</span></p>
        </div>

        @if ($this->isDownloading())
            <div wire:poll.1s="refreshDownload" class="space-y-2">
                <div class="h-2 overflow-hidden rounded-full bg-cream">
                    <div class="h-full rounded-full bg-brand transition-all" style="width: {{ $download['percent'] ?? 0 }}%"></div>
                </div>
                <p class="text-sm text-muted">
                    {{ ($download['state'] ?? '') === 'queued' ? 'Čakam na začetek prenosa …' : 'Prenašam … '.($download['percent'] ?? 0).' %' }}
                    Med prenosom lahko aplikacijo normalno uporabljaš.
                </p>
            </div>
        @endif

        @if ($updateMessage)
            <div @class([
                'rounded-2xl px-4 py-3 text-sm',
                'bg-brand/10 text-mauve-dark' => $updateAvailable,
                'bg-cream text-muted' => ! $updateAvailable,
            ])>
                <p>{{ $updateMessage }}</p>
                @if ($updateAvailable && $updateNotes)
                    <p class="mt-2 whitespace-pre-wrap text-ink/80">{{ $updateNotes }}</p>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <button type="button" class="btn-secondary" wire:click="checkForUpdates" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="checkForUpdates">Preveri posodobitve</span>
                <span wire:loading wire:target="checkForUpdates">Preverjam …</span>
            </button>

            @if ($updateAvailable && ! $this->isDownloading())
                <button type="button" class="btn-primary" wire:click="installUpdate" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="installUpdate">Prenesi in namesti {{ $latestVersion }}</span>
                    <span wire:loading wire:target="installUpdate">Začenjam …</span>
                </button>
            @endif
        </div>

    </section>
</div>
