<?php

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Klienti')] class extends Component
{
    public function with(): array
    {
        return [
            'clients' => Client::query()
                ->with('latestSession')
                ->withCount('sessions')
                ->orderBy('name')
                ->get(),
        ];
    }

    public function deleteClient(int $clientId): void
    {
        Client::query()->whereKey($clientId)->delete();
    }
};
?>

<div>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-script text-brand-light">tvoji klienti</p>
            <h2 class="text-3xl font-semibold tracking-tight text-ink">Klienti</h2>
            <p class="mt-2 max-w-xl text-sm text-muted">Izberi klienta za časovnico srečanj ali dodaj novega.</p>
        </div>

        <a href="{{ route('clients.create') }}" class="btn-primary" wire:navigate>Nov klient</a>
    </div>

    @if ($clients->isEmpty())
        <div class="app-card py-16 text-center">
            <h3 class="text-xl font-semibold tracking-tight text-ink">Še ni klientov</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">Začni z dodajanjem prvega klienta. Nato lahko vnašaš srečanja z narekom ali besedilom.</p>
            <a href="{{ route('clients.create') }}" class="btn-primary mt-6" wire:navigate>Dodaj klienta</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($clients as $client)
                <article class="app-card group flex flex-col gap-4 transition hover:-translate-y-0.5 hover:shadow-[0_16px_40px_rgba(69,9,32,0.1)]">
                    <a href="{{ route('clients.show', $client) }}" class="block space-y-3" wire:navigate>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xl font-semibold tracking-tight text-ink">{{ $client->name }}</h3>
                                <p class="mt-1 text-sm text-mauve-dark">{{ $client->resolvedTherapyType() }}</p>
                            </div>
                            <span class="rounded-full bg-cream px-3 py-1 text-xs text-muted">
                                {{ $client->sessions_count }} srečanj
                            </span>
                        </div>

                        @if ($client->latestSession)
                            <div>
                                <p class="font-script text-sm text-brand">zadnje srečanje</p>
                                <p class="text-sm text-ink/80">
                                    {{ $client->latestSession->session_date?->format('d. m. Y') }}
                                    @if ($client->latestSession->summary)
                                        — {{ \Illuminate\Support\Str::limit(strip_tags($client->latestSession->summary), 90) }}
                                    @endif
                                </p>
                            </div>
                        @else
                            <p class="text-sm text-muted">Še ni srečanj.</p>
                        @endif
                    </a>

                    <div class="mt-auto flex flex-wrap gap-2 border-t border-brand/10 pt-4">
                        <a href="{{ route('sessions.create', $client) }}" class="btn-primary" wire:navigate>Novo srečanje</a>
                        <a href="{{ route('clients.edit', $client) }}" class="btn-secondary" wire:navigate>Uredi</a>
                        <button type="button" wire:click="deleteClient({{ $client->id }})" wire:confirm="Res izbrišem klienta in vsa srečanja?" class="btn-ghost text-brand-light">Izbriši</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
