<?php

use App\Models\Client;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public ?Client $client = null;

    public string $name = '';

    public string $therapy_type = '';

    public string $notes = '';

    public string $defaultType = '';

    public function mount(?Client $client = null): void
    {
        $this->client = $client?->exists ? $client : null;
        $settings = Setting::current();
        $this->defaultType = $settings->default_therapy_type;

        if ($this->client) {
            $this->name = $this->client->name;
            $this->therapy_type = (string) ($this->client->therapy_type ?: '');
            $this->notes = (string) ($this->client->notes ?: '');
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'therapy_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['therapy_type'] = $validated['therapy_type'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        if ($this->client) {
            $this->client->update($validated);
            $client = $this->client;
        } else {
            $client = Client::query()->create($validated);
        }

        $this->redirect(route('clients.show', $client), navigate: true);
    }

    public function title(): string
    {
        return $this->client ? 'Uredi klienta' : 'Nov klient';
    }
};
?>

<div>
    <div class="mb-8">
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn-ghost -ml-3" wire:navigate>← Nazaj</a>
        <p class="mt-4 font-script text-brand-light">{{ $client ? 'urejanje' : 'nov začetek' }}</p>
        <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ $client ? 'Uredi klienta' : 'Nov klient' }}</h2>
    </div>

    <form wire:submit="save" class="app-card mx-auto max-w-2xl space-y-5">
        <div>
            <label class="field-label" for="name">Ime</label>
            <input id="name" type="text" wire:model="name" class="field-input" placeholder="npr. Ana" autofocus>
            @error('name') <p class="mt-1 text-sm text-brand-light">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label" for="therapy_type">Vrsta terapije</label>
            <select id="therapy_type" wire:model="therapy_type" class="field-input">
                <option value="">Privzeto: {{ $defaultType }}</option>
                @foreach (config('therapy.therapy_types') as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-muted">Če pustiš prazno, se uporabi privzeta vrsta iz nastavitev.</p>
        </div>

        <div>
            <label class="field-label" for="notes">Kratka opomba</label>
            <textarea id="notes" wire:model="notes" class="field-textarea" placeholder="Neobvezno …"></textarea>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn-primary">Shrani</button>
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary" wire:navigate>Prekliči</a>
        </div>
    </form>
</div>
