<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sebi naproti — zapiski' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    @if (! empty($hideNav))
        <main class="min-h-screen">
            {{ $slot }}
        </main>
    @else
        <div class="min-h-screen md:grid md:grid-cols-[240px_1fr]">
            <aside class="border-b border-brand/10 bg-white/80 backdrop-blur md:sticky md:top-0 md:h-screen md:border-b-0 md:border-r">
                <div class="flex h-full flex-col gap-8 p-5">
                    <div>
                        <p class="font-script text-brand-light">osebni prostor</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight leading-none text-ink">Sebi naproti</h1>
                        <p class="mt-2 text-sm text-muted">Zapiski srečanj</p>
                    </div>

                    <nav class="flex gap-2 md:flex-col">
                        <a href="{{ route('clients.index') }}"
                           class="nav-link {{ request()->routeIs('clients.*', 'sessions.*') ? 'nav-link-active' : '' }}">
                            <span>Klienti</span>
                        </a>
                        <a href="{{ route('settings') }}"
                           class="nav-link {{ request()->routeIs('settings') ? 'nav-link-active' : '' }}">
                            <span>Nastavitve</span>
                        </a>
                    </nav>

                    <div class="mt-auto hidden text-xs text-muted md:block">
                        Desktop app · podatki ostanejo lokalno
                    </div>
                </div>
            </aside>

            <main class="px-4 py-6 sm:px-8 md:px-10 md:py-8">
                <div class="mx-auto max-w-5xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    @endif

    @livewireScripts
</body>
</html>
