<?php

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public Client $client;

    public function mount(Client $client): void
    {
        $this->client = $client->load('sessions');
    }

    public function title(): string
    {
        return $this->client->name;
    }
};
?>

<div>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('clients.index') }}" class="btn-ghost -ml-3" wire:navigate>← Klienti</a>
            <p class="mt-4 font-script text-brand-light">časovnica</p>
            <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ $client->name }}</h2>
            <p class="mt-2 text-sm text-mauve-dark">{{ $client->resolvedTherapyType() }}</p>
            @if ($client->notes)
                <p class="mt-3 max-w-2xl text-sm text-muted">{{ $client->notes }}</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sessions.create', $client) }}" class="btn-primary" wire:navigate>Novo srečanje</a>
            <a href="{{ route('clients.edit', $client) }}" class="btn-secondary" wire:navigate>Uredi klienta</a>
        </div>
    </div>

    @if ($client->sessions->isEmpty())
        <div class="app-card py-14 text-center">
            <h3 class="text-xl font-semibold tracking-tight text-ink">Še ni srečanj</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">Dodaj prvo srečanje z narekom po seansi ali s kratkim zapiskom.</p>
            <a href="{{ route('sessions.create', $client) }}" class="btn-primary mt-6" wire:navigate>Dodaj srečanje</a>
        </div>
    @else
        <div class="relative space-y-0 pl-2">
            <div class="absolute bottom-4 left-[1.15rem] top-4 w-px bg-brand/25"></div>

            @foreach ($client->sessions as $session)
                <a href="{{ route('sessions.show', [$client, $session]) }}"
                   wire:key="session-{{ $session->id }}"
                   class="relative mb-5 flex gap-4 rounded-[1.25rem] p-1 transition hover:bg-white/60"
                   wire:navigate>
                    <div class="relative z-10 mt-6 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-brand bg-cream">
                        <span class="h-2 w-2 rounded-full bg-brand"></span>
                    </div>

                    <article class="app-card flex-1 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-script text-brand">{{ $session->session_date?->format('d. m. Y') }}</p>
                            <span @class([
                                'chip',
                                'bg-brand/15' => $session->status === 'ready',
                                'bg-amber-100 text-amber-800' => $session->status === 'processing',
                                'bg-red-100 text-red-700' => $session->status === 'failed',
                                'bg-cream text-muted' => $session->status === 'draft',
                            ])>
                                @switch($session->status)
                                    @case('ready') Pripravljeno @break
                                    @case('processing') Obdelava … @break
                                    @case('failed') Napaka @break
                                    @default Osnutek
                                @endswitch
                            </span>
                        </div>

                        <p class="text-sm leading-relaxed text-ink/85">
                            {{ $session->summary ? \Illuminate\Support\Str::limit(strip_tags($session->summary), 180) : ($session->transcript ? \Illuminate\Support\Str::limit($session->transcript, 180) : 'Brez povzetka zaenkrat.') }}
                        </p>

                        @if (! empty($session->topics))
                            <div class="flex flex-wrap gap-2">
                                @foreach ($session->topics as $topic)
                                    <span class="chip">{{ $topic }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                </a>
            @endforeach
        </div>
    @endif
</div>
