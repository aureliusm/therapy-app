<?php

use App\Models\Setting;
use App\Services\AppUpdater;
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

    public function mount(AppUpdater $updater): void
    {
        $settings = Setting::current();
        $this->default_therapy_type = $settings->default_therapy_type;
        $this->base_prompt = (string) $settings->base_prompt;
        $this->currentVersion = $updater->currentVersion();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'default_therapy_type' => ['required', 'string', 'max:255'],
            'base_prompt' => ['required', 'string'],
        ]);

        Setting::current()->update($validated);
        session()->flash('status', 'Nastavitve so shranjene.');
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

        try {
            $this->updateMessage = $updater->install($status['latest']);
        } catch (\Throwable $e) {
            $this->updateMessage = $e->getMessage();
        }
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

        <div class="rounded-2xl bg-cream p-4 text-sm text-muted">
            <p class="font-medium text-ink/80">Gemini</p>
            <p class="mt-1">Ključ in modeli so v <code class="text-mauve-dark">.env</code> (<code>GEMINI_API_KEY</code>, <code>GEMINI_SUMMARY_MODEL</code>, <code>GEMINI_TRANSCRIBE_MODEL</code>).</p>
        </div>

        <button type="submit" class="btn-primary">Shrani nastavitve</button>
    </form>

    <section class="app-card mx-auto mt-6 max-w-3xl space-y-4">
        <div>
            <p class="font-script text-brand">posodobitve</p>
            <h3 class="mt-1 text-xl font-semibold tracking-tight text-ink">Posodobitve aplikacije</h3>
            <p class="mt-1 text-sm text-muted">Trenutna različica: <span class="font-medium text-ink">{{ $currentVersion }}</span></p>
        </div>

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

            @if ($updateAvailable)
                <button type="button" class="btn-primary" wire:click="installUpdate" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="installUpdate">Prenesi in namesti {{ $latestVersion }}</span>
                    <span wire:loading wire:target="installUpdate">Prenašam …</span>
                </button>
            @endif
        </div>

    </section>
</div>
